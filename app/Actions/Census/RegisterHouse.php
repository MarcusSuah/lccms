<?php

namespace App\Actions\Census;

use App\Exceptions\CensusActionException;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Geography\Block;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusReferenceCodes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Adds a physical house to the block register, or updates one the enumerator
 * is authorised to edit. Houses are campaign-independent, but registering one
 * requires an active enumerator assignment covering the block.
 */
class RegisterHouse
{
    public function __construct(
        private CensusAccess $access,
        private CensusReferenceCodes $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated with CensusRules::house()
     */
    public function create(User $actor, CensusCampaign $campaign, Block $block, array $data, ?string $uuid = null): House
    {
        if ($this->access->enumeratorAssignment($actor, $campaign, $block->pathAttributes()) === null) {
            throw CensusActionException::unauthorized();
        }

        return DB::transaction(function () use ($block, $data, $uuid): House {
            $house = new House($data);
            $house->forceFill([
                ...$block->pathAttributes(),
                'uuid' => $uuid ?? (string) Str::uuid(),
                'reference_code' => $this->codes->forHouse($block),
            ])->save();

            return $house;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, CensusCampaign $campaign, House $house, array $data): House
    {
        if ($this->access->enumeratorAssignment($actor, $campaign, $house->geoPath()) === null) {
            throw CensusActionException::unauthorized();
        }

        $house->fill($data)->save();

        return $house;
    }
}
