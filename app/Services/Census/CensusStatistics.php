<?php

namespace App\Services\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\OccupancyStatus;
use App\Enums\Geography\GeoLevel;
use App\Models\Census\CensusCampaign;
use App\Models\Census\HouseEnumeration;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Population and housing figures computed with SQL aggregates directly from
 * individual records. Nothing here is manually editable: official totals are
 * the count of approved occupant rows.
 *
 * Status buckets are cumulative: "submitted" includes verified and approved,
 * "verified" includes approved. "Registered" counts every non-archived record.
 */
class CensusStatistics
{
    /**
     * Age in completed years at the campaign reference date, falling back to
     * the recorded (possibly estimated) age. Bind the reference date once.
     */
    public const AGE_AT_REFERENCE_DATE = 'COALESCE(TIMESTAMPDIFF(YEAR, date_of_birth, ?), age_years)';

    public function __construct(private CensusAccess $access) {}

    public static function invalidate(int $campaignId): void
    {
        Cache::forever(self::versionKey($campaignId), self::version($campaignId) + 1);
    }

    public static function version(int $campaignId): int
    {
        return (int) Cache::get(self::versionKey($campaignId), 1);
    }

    private static function versionKey(int $campaignId): string
    {
        return "census-stats:{$campaignId}:version";
    }

    /**
     * Approved-only figures for public pages, cached until the next approval.
     *
     * @param  array<string, int>  $area  e.g. ['community_id' => 5]
     * @return array<string, mixed>
     */
    public function publicSummary(CensusCampaign $campaign, array $area = []): array
    {
        $key = sprintf('census-stats:%d:v%d:public:%s', $campaign->id, self::version($campaign->id), md5(serialize($area)));

        return Cache::remember($key, now()->addHour(), function () use ($campaign, $area): array {
            $summary = $this->summary($campaign, null, $area);

            return [
                'approved_occupants' => $summary['occupants']['approved'],
                'approved_households' => $summary['households']['approved'],
                'approved_houses' => $summary['houses']['approved'],
                'average_household_size' => $summary['average_household_size'],
                'sex' => $summary['sex'],
                'age_groups' => $summary['age_groups'],
                'is_enumerated' => $summary['houses']['visited'] > 0,
            ];
        });
    }

    /**
     * @param  array<string, int>  $area  optional path filter, e.g. ['district_id' => 3]
     * @return array<string, mixed>
     */
    public function summary(CensusCampaign $campaign, ?User $user, array $area = []): array
    {
        $occupants = $this->statusCounts($this->base(Occupant::query(), $campaign, $user, $area));
        $households = $this->statusCounts($this->base(Household::query(), $campaign, $user, $area));

        $houses = $this->base(HouseEnumeration::query(), $campaign, $user, $area)
            ->toBase()
            ->selectRaw('COUNT(*) AS visited')
            ->selectRaw('SUM(status = ?) AS approved', [CensusRecordStatus::Approved->value])
            ->selectRaw('SUM(occupancy_status = ?) AS occupied', [OccupancyStatus::Occupied->value])
            ->selectRaw('SUM(occupancy_status = ?) AS vacant', [OccupancyStatus::Vacant->value])
            ->selectRaw('SUM(occupancy_status IN (?, ?)) AS not_enumerated', [OccupancyStatus::Refused->value, OccupancyStatus::NotContacted->value])
            ->where('status', '!=', CensusRecordStatus::Archived->value)
            ->first();

        $approvedOccupants = $this->base(Occupant::query(), $campaign, $user, $area)
            ->where('status', CensusRecordStatus::Approved);

        $sex = (clone $approvedOccupants)->toBase()
            ->select('sex', DB::raw('COUNT(*) AS total'))
            ->groupBy('sex')
            ->pluck('total', 'sex')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $ageExpression = self::AGE_AT_REFERENCE_DATE;
        $ageGroups = (clone $approvedOccupants)->toBase()
            ->selectRaw("CASE
                WHEN {$ageExpression} IS NULL THEN 'unknown'
                WHEN {$ageExpression} < 5 THEN '0-4'
                WHEN {$ageExpression} < 15 THEN '5-14'
                WHEN {$ageExpression} < 25 THEN '15-24'
                WHEN {$ageExpression} < 45 THEN '25-44'
                WHEN {$ageExpression} < 65 THEN '45-64'
                ELSE '65+' END AS age_group", array_fill(0, 6, $campaign->reference_date->toDateString()))
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('age_group')
            ->pluck('total', 'age_group')
            ->map(fn ($total): int => (int) $total)
            ->all();

        $demographics = (clone $approvedOccupants)->toBase()
            ->selectRaw("SUM({$ageExpression} >= 18) AS adults", [$campaign->reference_date->toDateString()])
            ->selectRaw("SUM({$ageExpression} < 18) AS children", [$campaign->reference_date->toDateString()])
            ->selectRaw("SUM({$ageExpression} IS NULL) AS age_unknown", [$campaign->reference_date->toDateString()])
            ->first();

        return [
            'occupants' => $occupants,
            'adults' => (int) ($demographics->adults ?? 0),
            'children' => (int) ($demographics->children ?? 0),
            'age_unknown' => (int) ($demographics->age_unknown ?? 0),
            'households' => $households,
            'houses' => [
                'visited' => (int) ($houses->visited ?? 0),
                'approved' => (int) ($houses->approved ?? 0),
                'occupied' => (int) ($houses->occupied ?? 0),
                'vacant' => (int) ($houses->vacant ?? 0),
                'not_enumerated' => (int) ($houses->not_enumerated ?? 0),
            ],
            'average_household_size' => $households['approved'] > 0
                ? round($occupants['approved'] / $households['approved'], 2)
                : null,
            'sex' => $sex,
            'age_groups' => array_merge(array_fill_keys(['0-4', '5-14', '15-24', '25-44', '45-64', '65+'], 0), $ageGroups),
        ];
    }

    /**
     * Figures per area at one level, e.g. every district of a county.
     * Areas with no visit records are reported as not enumerated rather than zero.
     *
     * @param  array<string, int>  $parent  path filter for the parent area
     * @return list<array<string, mixed>>
     */
    public function breakdown(CensusCampaign $campaign, ?User $user, GeoLevel $level, array $parent = []): array
    {
        $column = $level->column();

        $occupants = $this->groupedStatusCounts($this->base(Occupant::query(), $campaign, $user, $parent), $column);
        $households = $this->groupedStatusCounts($this->base(Household::query(), $campaign, $user, $parent), $column);
        $houses = $this->base(HouseEnumeration::query(), $campaign, $user, $parent)
            ->toBase()
            ->where('status', '!=', CensusRecordStatus::Archived->value)
            ->select($column, DB::raw('COUNT(*) AS visited'), DB::raw("SUM(status = 'approved') AS approved"))
            ->groupBy($column)
            ->get()
            ->keyBy($column);

        $areaClass = $level->modelClass();
        $areas = $areaClass::query()
            ->when($parent !== [], function (Builder $query) use ($parent): void {
                foreach ($parent as $parentColumn => $id) {
                    $query->where($parentColumn, $id);
                }
            })
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'reference_code']);

        return $areas->map(function ($area) use ($occupants, $households, $houses): array {
            $visit = $houses->get($area->id);
            $people = $occupants[$area->id] ?? $this->emptyCounts();
            $homes = $households[$area->id] ?? $this->emptyCounts();

            return [
                'id' => $area->id,
                'name' => $area->name,
                'reference_code' => $area->reference_code,
                'is_enumerated' => $visit !== null,
                'houses_visited' => (int) ($visit->visited ?? 0),
                'houses_approved' => (int) ($visit->approved ?? 0),
                'households' => $homes,
                'occupants' => $people,
                'average_household_size' => $homes['approved'] > 0 ? round($people['approved'] / $homes['approved'], 2) : null,
            ];
        })->values()->all();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, int>  $area
     * @return Builder<TModel>
     */
    private function base(Builder $query, CensusCampaign $campaign, ?User $user, array $area): Builder
    {
        $query->where($query->getModel()->qualifyColumn('campaign_id'), $campaign->id);

        foreach ($area as $column => $id) {
            $query->where($query->getModel()->qualifyColumn($column), $id);
        }

        if ($user !== null) {
            $this->access->applyToRecords($query, $user);
        }

        return $query;
    }

    /**
     * @return array{registered: int, submitted: int, verified: int, approved: int, rejected: int, draft: int}
     */
    private function statusCounts(Builder $query): array
    {
        $row = $query->toBase()->selectRaw($this->statusSelect())->first();

        return $this->normalizeCounts($row);
    }

    /**
     * @return array<int, array{registered: int, submitted: int, verified: int, approved: int, rejected: int, draft: int}>
     */
    private function groupedStatusCounts(Builder $query, string $column): array
    {
        return $query->toBase()
            ->select($column)
            ->selectRaw($this->statusSelect())
            ->groupBy($column)
            ->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->{$column} => $this->normalizeCounts($row)])
            ->all();
    }

    private function statusSelect(): string
    {
        return "SUM(status <> 'archived') AS registered,
            SUM(status IN ('submitted', 'verified', 'approved')) AS submitted,
            SUM(status IN ('verified', 'approved')) AS verified,
            SUM(status = 'approved') AS approved,
            SUM(status = 'rejected') AS rejected,
            SUM(status = 'draft') AS draft";
    }

    /**
     * @return array{registered: int, submitted: int, verified: int, approved: int, rejected: int, draft: int}
     */
    private function normalizeCounts(?object $row): array
    {
        $counts = $this->emptyCounts();

        foreach (array_keys($counts) as $key) {
            $counts[$key] = (int) ($row->{$key} ?? 0);
        }

        return $counts;
    }

    /**
     * @return array{registered: int, submitted: int, verified: int, approved: int, rejected: int, draft: int}
     */
    private function emptyCounts(): array
    {
        return ['registered' => 0, 'submitted' => 0, 'verified' => 0, 'approved' => 0, 'rejected' => 0, 'draft' => 0];
    }
}
