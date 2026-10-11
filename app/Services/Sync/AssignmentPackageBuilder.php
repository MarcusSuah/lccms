<?php

namespace App\Services\Sync;

use App\Enums\Census\AssignmentRole;
use App\Enums\Census\BuildingType;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\OccupancyStatus;
use App\Enums\Census\RelationshipToHead;
use App\Enums\Census\ResidenceType;
use App\Enums\Census\SchoolAttendance;
use App\Enums\Census\Sex;
use App\Enums\Census\Tenure;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\HouseEnumeration;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Geography\Block;
use App\Models\User;
use App\Services\Census\CensusAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds the minimal data an enumerator needs to work offline: the blocks of
 * their current assignments, the houses on them (without coordinates), and
 * only their own households and occupants for this campaign.
 */
class AssignmentPackageBuilder
{
    public const MAX_HOUSES = 20000;

    public function __construct(private CensusAccess $access) {}

    /**
     * @return array<string, mixed>
     */
    public function build(User $user, CensusCampaign $campaign, ?Carbon $since = null): array
    {
        $assignments = $this->access->currentAssignments($user)
            ->where('campaign_id', $campaign->id)
            ->where('role', AssignmentRole::Enumerator)
            ->values();

        $blocks = $this->blocks($assignments);
        $blockIds = $blocks->pluck('id')->all();

        $houses = House::query()
            ->whereIn('block_id', $blockIds === [] ? [0] : $blockIds)
            ->where('status', 'active')
            ->when($since, fn (Builder $query) => $query->where('updated_at', '>=', $since))
            ->orderBy('id')
            ->limit(self::MAX_HOUSES)
            ->get(['id', 'uuid', 'reference_code', 'block_id', 'local_house_number', 'building_type', 'tenure', 'floors', 'version', 'updated_at']);

        $own = fn (Builder $query) => $query
            ->where('campaign_id', $campaign->id)
            ->where('enumerator_id', $user->id)
            ->when($since, fn (Builder $inner) => $inner->where('updated_at', '>=', $since));

        $visits = HouseEnumeration::query()->tap($own)->get(['id', 'uuid', 'house_id', 'occupancy_status', 'status', 'visited_at', 'notes', 'version']);
        $households = Household::query()->tap($own)->get();
        $occupants = Occupant::query()->tap($own)->where('status', '!=', CensusRecordStatus::Archived)->get();

        return [
            'campaign' => [
                'id' => $campaign->id,
                'reference_code' => $campaign->reference_code,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'reference_date' => $campaign->reference_date->toDateString(),
                'end_date' => $campaign->end_date->toDateString(),
                'instructions' => $campaign->instructions,
            ],
            'assignments' => $assignments->map(fn (CampaignAssignment $assignment): array => [
                'id' => $assignment->id,
                'level' => $assignment->level()->value,
                'area_id' => $assignment->areaId(),
                'starts_on' => $assignment->starts_on->toDateString(),
                'ends_on' => $assignment->ends_on->toDateString(),
            ])->all(),
            'blocks' => $blocks->all(),
            'houses' => $houses->map(fn (House $house): array => [
                'id' => $house->id,
                'uuid' => $house->uuid,
                'reference_code' => $house->reference_code,
                'block_id' => $house->block_id,
                'local_house_number' => $house->local_house_number,
                'building_type' => $house->building_type?->value,
                'tenure' => $house->tenure?->value,
                'floors' => $house->floors,
                'version' => $house->version,
            ])->all(),
            'visits' => $visits->map(fn (HouseEnumeration $visit): array => [
                'id' => $visit->id,
                'uuid' => $visit->uuid,
                'house_id' => $visit->house_id,
                'occupancy_status' => $visit->occupancy_status->value,
                'status' => $visit->status->value,
                'visited_at' => $visit->visited_at?->toIso8601String(),
                'notes' => $visit->notes,
                'version' => $visit->version,
            ])->all(),
            'households' => $households->map(fn (Household $household): array => [
                'id' => $household->id,
                'uuid' => $household->uuid,
                'reference_code' => $household->reference_code,
                'house_id' => $household->house_id,
                'sequence_in_house' => $household->sequence_in_house,
                'contact_name' => $household->contact_name,
                'contact_phone' => $household->contact_phone,
                'residence_type' => $household->residence_type->value,
                'status' => $household->status->value,
                'review_note' => $household->review_note,
                'version' => $household->version,
            ])->all(),
            'occupants' => $occupants->map(fn (Occupant $occupant): array => [
                'id' => $occupant->id,
                'uuid' => $occupant->uuid,
                'reference_code' => $occupant->reference_code,
                'household_id' => $occupant->household_id,
                'full_name' => $occupant->full_name,
                'date_of_birth' => $occupant->date_of_birth?->toDateString(),
                'age_years' => $occupant->age_years,
                'age_is_estimated' => $occupant->age_is_estimated,
                'sex' => $occupant->sex->value,
                'relationship_to_head' => $occupant->relationship_to_head->value,
                'usual_resident' => $occupant->usual_resident,
                'school_attendance' => $occupant->school_attendance?->value,
                'status' => $occupant->status->value,
                'version' => $occupant->version,
            ])->all(),
            'options' => [
                'building_type' => BuildingType::options(),
                'tenure' => Tenure::options(),
                'occupancy_status' => OccupancyStatus::options(),
                'residence_type' => ResidenceType::options(),
                'sex' => Sex::options(),
                'relationship_to_head' => RelationshipToHead::options(),
                'school_attendance' => SchoolAttendance::options(),
            ],
            'truncated' => $houses->count() >= self::MAX_HOUSES,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, CampaignAssignment>  $assignments
     * @return Collection<int, array<string, mixed>>
     */
    private function blocks(Collection $assignments): Collection
    {
        if ($assignments->isEmpty()) {
            return collect();
        }

        return Block::query()
            ->active()
            ->where(function (Builder $query) use ($assignments): void {
                foreach ($assignments as $assignment) {
                    $column = $assignment->level()->value === 'block' ? 'id' : $assignment->level()->column();
                    $query->orWhere($column, $assignment->areaId());
                }
            })
            ->with(['community:id,name', 'town:id,name'])
            ->orderBy('community_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Block $block): array => [
                'id' => $block->id,
                'name' => $block->name,
                'reference_code' => $block->reference_code,
                'community_id' => $block->community_id,
                'community' => $block->community?->name,
                'town' => $block->town?->name,
            ]);
    }
}
