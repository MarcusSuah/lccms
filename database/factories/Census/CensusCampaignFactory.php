<?php

namespace Database\Factories\Census;

use App\Enums\Census\CampaignStatus;
use App\Models\Census\CensusCampaign;
use App\Models\Geography\GeoArea;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CensusCampaign>
 */
class CensusCampaignFactory extends Factory
{
    protected $model = CensusCampaign::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_code' => 'DEV'.fake()->unique()->numerify('####'),
            'name' => 'Development campaign '.fake()->unique()->numberBetween(1, 99999),
            'description' => 'Fixture campaign for development and tests.',
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'reference_date' => now()->subMonth()->toDateString(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => CampaignStatus::Active]);
    }

    public function inStatus(CampaignStatus $status): static
    {
        return $this->state(fn (array $attributes): array => ['status' => $status]);
    }

    /**
     * Cover the given area.
     */
    public function covering(GeoArea $area): static
    {
        return $this->afterCreating(function (CensusCampaign $campaign) use ($area): void {
            $campaign->scopes()->create(app(GeoAreaResolver::class)->fullPath($area));
        });
    }
}
