<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\Sex;
use App\Models\Census\Occupant;
use App\Models\User;
use App\Services\Census\CensusStatistics;
use App\Services\Reports\ReportDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Approved occupants by age group and sex: the table behind a population pyramid.
 */
class AgeSexReport extends ReportDefinition
{
    private const GROUPS = ['0-4', '5-14', '15-24', '25-44', '45-64', '65+', 'unknown'];

    public function key(): string
    {
        return 'age-sex';
    }

    public function title(): string
    {
        return 'Age and sex structure';
    }

    public function description(): string
    {
        return 'Approved occupants by age group (at the campaign reference date) and sex.';
    }

    public function category(): string
    {
        return 'Census';
    }

    public function authorize(User $user): bool
    {
        return $user->can(Permission::CensusView->value) || $user->can(Permission::ReportsView->value);
    }

    public function filters(): array
    {
        return ['campaign' => [], 'area' => []];
    }

    public function columns(array $filters): array
    {
        return [
            self::text('age_group', 'Age group'),
            ...array_map(fn (Sex $sex): array => self::number($sex->value, $sex->label()), Sex::cases()),
            self::number('total', 'Total'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        if ($filters['campaign'] === null) {
            return [];
        }

        $campaign = $filters['campaign'];
        $age = CensusStatistics::AGE_AT_REFERENCE_DATE;
        $referenceDate = $campaign->reference_date->toDateString();

        $counts = $this->wherePath(Occupant::query()->accessibleBy($user), $filters['area_path'])
            ->where('campaign_id', $campaign->id)
            ->where('status', CensusRecordStatus::Approved)
            ->toBase()
            ->selectRaw("CASE
                WHEN {$age} IS NULL THEN 'unknown'
                WHEN {$age} < 5 THEN '0-4'
                WHEN {$age} < 15 THEN '5-14'
                WHEN {$age} < 25 THEN '15-24'
                WHEN {$age} < 45 THEN '25-44'
                WHEN {$age} < 65 THEN '45-64'
                ELSE '65+' END AS age_group", array_fill(0, 6, $referenceDate))
            ->addSelect('sex', DB::raw('COUNT(*) AS total'))
            ->groupBy('age_group', 'sex')
            ->get();

        $rows = [];

        foreach ([...self::GROUPS, 'total'] as $group) {
            $row = ['age_group' => match ($group) {
                'unknown' => 'Age not recorded',
                'total' => 'All ages',
                default => $group,
            }];

            foreach (Sex::cases() as $sex) {
                $row[$sex->value] = (int) $counts
                    ->when($group !== 'total', fn ($rows) => $rows->where('age_group', $group))
                    ->where('sex', $sex->value)
                    ->sum('total');
            }

            $row['total'] = array_sum(array_slice($row, 1));
            $rows[] = $row;
        }

        return $rows;
    }
}
