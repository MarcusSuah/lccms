<?php

namespace App\Actions\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\OccupancyStatus;
use App\Exceptions\CensusActionException;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\HouseEnumeration;
use App\Models\Census\Household;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusReferenceCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates or edits a household in a house for one campaign. Several
 * households may share one house.
 */
class SaveHousehold
{
    public function __construct(
        private CensusAccess $access,
        private CensusReferenceCodes $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated with CensusRules::household()
     */
    public function create(User $actor, CensusCampaign $campaign, House $house, array $data, ?string $uuid = null): Household
    {
        $assignment = $this->access->enumeratorAssignment($actor, $campaign, $house->geoPath());

        if ($assignment === null) {
            throw CensusActionException::unauthorized();
        }

        return DB::transaction(function () use ($actor, $campaign, $house, $data, $uuid, $assignment): Household {
            $visit = HouseEnumeration::query()
                ->where('house_id', $house->id)
                ->where('campaign_id', $campaign->id)
                ->lockForUpdate()
                ->first();

            if ($visit === null) {
                $visit = new HouseEnumeration(['occupancy_status' => OccupancyStatus::Occupied]);
                $visit->forceFill([
                    ...$house->geoPath(),
                    'uuid' => (string) Str::uuid(),
                    'house_id' => $house->id,
                    'campaign_id' => $campaign->id,
                    'enumerator_id' => $actor->id,
                    'supervisor_id' => $assignment->supervisor_id,
                    'visited_at' => now(),
                ])->save();
            } elseif ($visit->occupancy_status !== OccupancyStatus::Occupied) {
                throw new CensusActionException(CensusActionException::INVALID_PARENT, 'This house was recorded as '.$visit->occupancy_status->label().'. Record it as occupied before adding households.');
            }

            $household = new Household($data);
            $household->forceFill([
                ...$house->geoPath(),
                ...$this->codes->forHousehold($house, $campaign),
                'uuid' => $uuid ?? (string) Str::uuid(),
                'house_id' => $house->id,
                'campaign_id' => $campaign->id,
                'enumerator_id' => $actor->id,
                'supervisor_id' => $assignment->supervisor_id,
                'status' => CensusRecordStatus::Draft,
            ])->save();

            return $household;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Household $household, array $data): Household
    {
        $this->assertFieldEditable($actor, $household);

        $household->fill($data)->save();

        return $household;
    }

    public function assertFieldEditable(User $actor, Household $household): void
    {
        $household->loadMissing('campaign.scopes');

        if ($this->access->enumeratorAssignment($actor, $household->campaign, $household->geoPath()) === null) {
            throw CensusActionException::unauthorized();
        }

        if ($household->enumerator_id !== $actor->id) {
            throw CensusActionException::unauthorized('Only the enumerator who registered this household can change it in the field.');
        }

        if (! $household->status->isEditableInField()) {
            throw new CensusActionException(CensusActionException::NOT_EDITABLE, 'This household has been '.$household->status->label().' and can only be changed through a correction request.');
        }
    }
}
