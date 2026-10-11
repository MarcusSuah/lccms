<?php

namespace App\Services\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\CorrectionStatus;
use App\Enums\Census\DuplicateStatus;
use App\Enums\Census\OccupancyStatus;
use App\Enums\Census\RelationshipToHead;
use App\Models\Census\CensusCampaign;
use App\Models\Census\DuplicateFlag;
use App\Models\Census\HouseEnumeration;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Census\RecordCorrection;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Reconciliation checks between occupants, households, houses and the
 * campaign. Every check is a single aggregate query; zero means clean.
 */
class CensusDataQuality
{
    public function __construct(private CensusAccess $access) {}

    /**
     * @return list<array{key: string, label: string, count: int, severity: string}>
     */
    public function report(CensusCampaign $campaign, ?User $user): array
    {
        $scoped = function (Builder $query) use ($campaign, $user): Builder {
            $query->where($query->getModel()->qualifyColumn('campaign_id'), $campaign->id);

            return $user === null ? $query : $this->access->applyToRecords($query, $user);
        };

        $active = [CensusRecordStatus::Draft, CensusRecordStatus::Submitted, CensusRecordStatus::Verified, CensusRecordStatus::Approved, CensusRecordStatus::Rejected];

        return [
            [
                'key' => 'households_without_occupants',
                'label' => 'Households with no occupants',
                'count' => $scoped(Household::query())
                    ->whereIn('status', $active)
                    ->whereDoesntHave('occupants', fn (Builder $query) => $query->where('status', '!=', CensusRecordStatus::Archived))
                    ->count(),
                'severity' => 'error',
            ],
            [
                'key' => 'households_without_head',
                'label' => 'Households with no head',
                'count' => $scoped(Household::query())
                    ->whereIn('status', $active)
                    ->whereDoesntHave('occupants', fn (Builder $query) => $query
                        ->where('relationship_to_head', RelationshipToHead::Head)
                        ->where('status', '!=', CensusRecordStatus::Archived))
                    ->count(),
                'severity' => 'warning',
            ],
            [
                'key' => 'occupant_path_mismatch',
                'label' => 'Occupants whose location differs from their household',
                'count' => $scoped(Occupant::query())
                    ->join('households', 'households.id', '=', 'occupants.household_id')
                    ->where(fn (Builder $query) => $query
                        ->whereColumn('occupants.block_id', '!=', 'households.block_id')
                        ->orWhereColumn('occupants.county_id', '!=', 'households.county_id'))
                    ->count(),
                'severity' => 'error',
            ],
            [
                'key' => 'occupied_without_households',
                'label' => 'Houses recorded as occupied with no household',
                'count' => $scoped(HouseEnumeration::query())
                    ->where('occupancy_status', OccupancyStatus::Occupied)
                    ->where('status', '!=', CensusRecordStatus::Archived)
                    ->whereNotExists(fn ($query) => $query->selectRaw('1')
                        ->from('households')
                        ->whereColumn('households.house_id', 'house_enumerations.house_id')
                        ->whereColumn('households.campaign_id', 'house_enumerations.campaign_id')
                        ->where('households.status', '!=', CensusRecordStatus::Archived->value))
                    ->count(),
                'severity' => 'warning',
            ],
            [
                'key' => 'pending_review',
                'label' => 'Households awaiting verification or approval',
                'count' => $scoped(Household::query())
                    ->whereIn('status', [CensusRecordStatus::Submitted, CensusRecordStatus::Verified])
                    ->count(),
                'severity' => 'info',
            ],
            [
                'key' => 'returned',
                'label' => 'Households returned to enumerators',
                'count' => $scoped(Household::query())->where('status', CensusRecordStatus::Rejected)->count(),
                'severity' => 'info',
            ],
            [
                'key' => 'open_duplicates',
                'label' => 'Possible duplicates awaiting review',
                'count' => DuplicateFlag::query()
                    ->where('campaign_id', $campaign->id)
                    ->where('status', DuplicateStatus::Open)
                    ->whereHas('occupant', fn (Builder $query) => $user === null ? $query : $this->access->applyToRecords($query, $user))
                    ->count(),
                'severity' => 'warning',
            ],
            [
                'key' => 'pending_corrections',
                'label' => 'Corrections awaiting a decision',
                'count' => RecordCorrection::query()
                    ->where('campaign_id', $campaign->id)
                    ->where('status', CorrectionStatus::Pending)
                    ->count(),
                'severity' => 'info',
            ],
        ];
    }
}
