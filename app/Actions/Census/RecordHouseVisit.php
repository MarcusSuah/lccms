<?php

namespace App\Actions\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Exceptions\CensusActionException;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\HouseEnumeration;
use App\Models\User;
use App\Services\Census\CensusAccess;
use Illuminate\Support\Str;

/**
 * Records the outcome of visiting a house in a campaign (occupied, vacant,
 * refused …). One visit record per house per campaign.
 */
class RecordHouseVisit
{
    public function __construct(private CensusAccess $access) {}

    /**
     * @param  array<string, mixed>  $data  validated with CensusRules::houseVisit()
     */
    public function handle(User $actor, CensusCampaign $campaign, House $house, array $data, ?string $uuid = null): HouseEnumeration
    {
        $assignment = $this->access->enumeratorAssignment($actor, $campaign, $house->geoPath());

        if ($assignment === null) {
            throw CensusActionException::unauthorized();
        }

        $visit = HouseEnumeration::query()
            ->where('house_id', $house->id)
            ->where('campaign_id', $campaign->id)
            ->first();

        if ($visit !== null) {
            if ($visit->enumerator_id !== $actor->id) {
                throw CensusActionException::unauthorized('Another enumerator recorded this house visit.');
            }

            if (! $visit->status->isEditableInField()) {
                throw new CensusActionException(CensusActionException::NOT_EDITABLE, 'This visit has been reviewed and can no longer be changed in the field.');
            }

            $visit->fill($data)->save();

            return $visit;
        }

        $visit = new HouseEnumeration($data);
        $visit->forceFill([
            ...$house->geoPath(),
            'uuid' => $uuid ?? (string) Str::uuid(),
            'house_id' => $house->id,
            'campaign_id' => $campaign->id,
            'enumerator_id' => $actor->id,
            'supervisor_id' => $assignment->supervisor_id,
            'status' => CensusRecordStatus::Draft,
            'visited_at' => $data['visited_at'] ?? now(),
        ])->save();

        return $visit;
    }
}
