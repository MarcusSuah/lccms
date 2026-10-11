<?php

namespace Database\Factories\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\ResidenceType;
use App\Models\Census\CensusCampaign;
use App\Models\Census\House;
use App\Models\Census\Household;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Household>
 */
class HouseholdFactory extends Factory
{
    protected $model = Household::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'residence_type' => ResidenceType::Usual,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Household $household): void {
            $house = $household->house_id !== null ? House::query()->findOrFail($household->house_id) : House::factory()->create();
            $campaignId = $household->campaign_id ?? CensusCampaign::factory()->create()->id;
            $sequence = (int) Household::query()->where('house_id', $house->id)->where('campaign_id', $campaignId)->max('sequence_in_house') + 1;

            $household->forceFill([
                ...$house->geoPath(),
                'house_id' => $house->id,
                'campaign_id' => $campaignId,
                'uuid' => $household->uuid ?? (string) Str::uuid(),
                'reference_code' => $household->reference_code ?? 'DEV-HH-'.Str::upper(Str::random(12)),
                'sequence_in_house' => $household->sequence_in_house ?? $sequence,
                'enumerator_id' => $household->enumerator_id ?? User::factory()->create()->id,
                'status' => $household->status ?? CensusRecordStatus::Draft,
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

    public function inStatus(CensusRecordStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }
}
