<?php

namespace Database\Factories\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\OccupancyStatus;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\HouseEnumeration;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<HouseEnumeration>
 */
class HouseEnumerationFactory extends Factory
{
    protected $model = HouseEnumeration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'occupancy_status' => OccupancyStatus::Occupied,
            'visited_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (HouseEnumeration $visit): void {
            $house = $visit->house_id !== null ? House::query()->findOrFail($visit->house_id) : House::factory()->create();

            $visit->forceFill([
                ...$house->geoPath(),
                'house_id' => $house->id,
                'uuid' => $visit->uuid ?? (string) Str::uuid(),
                'campaign_id' => $visit->campaign_id ?? CensusCampaign::factory()->create()->id,
                'enumerator_id' => $visit->enumerator_id ?? User::factory()->create()->id,
                'status' => $visit->status ?? CensusRecordStatus::Draft,
            ]);
        });
    }

    public function forHouse(House $house, CensusCampaign $campaign, User $enumerator): static
    {
        return $this->state(fn (array $attributes): array => [
            'house_id' => $house->id,
            'campaign_id' => $campaign->id,
            'enumerator_id' => $enumerator->id,
        ]);
    }
}
