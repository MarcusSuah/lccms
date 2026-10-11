<?php

namespace App\Services\Census;

use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Geography\Block;

/**
 * Server-controlled, human-readable references for census records. Offline
 * clients never choose these; they identify records by UUID until synced.
 * Each method must run inside a transaction: it locks the parent row.
 */
class CensusReferenceCodes
{
    public function forHouse(Block $block): string
    {
        Block::query()->whereKey($block->id)->lockForUpdate()->first();

        $sequence = House::query()->where('block_id', $block->id)->count() + 1;

        do {
            $code = sprintf('%s-H%04d', $block->reference_code, $sequence++);
        } while (House::query()->where('reference_code', $code)->exists());

        return $code;
    }

    /**
     * @return array{reference_code: string, sequence_in_house: int}
     */
    public function forHousehold(House $house, CensusCampaign $campaign): array
    {
        House::query()->whereKey($house->id)->lockForUpdate()->first();

        $sequence = (int) Household::query()
            ->where('house_id', $house->id)
            ->where('campaign_id', $campaign->id)
            ->max('sequence_in_house') + 1;

        return [
            'reference_code' => sprintf('%s/%s-%02d', $house->reference_code, $campaign->reference_code, $sequence),
            'sequence_in_house' => $sequence,
        ];
    }

    public function forOccupant(Household $household): string
    {
        Household::query()->whereKey($household->id)->lockForUpdate()->first();

        $sequence = Occupant::query()->where('household_id', $household->id)->count() + 1;

        do {
            $code = sprintf('%s-P%02d', $household->reference_code, $sequence++);
        } while (Occupant::query()->where('reference_code', $code)->exists());

        return $code;
    }
}
