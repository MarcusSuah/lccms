<?php

namespace Database\Factories\Census;

use App\Enums\Census\AssignmentRole;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\Geography\GeoArea;
use App\Models\User;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignAssignment>
 */
class CampaignAssignmentFactory extends Factory
{
    protected $model = CampaignAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'campaign_id' => CensusCampaign::factory(),
            'user_id' => User::factory(),
            'role' => AssignmentRole::Enumerator,
            'starts_on' => now()->subWeek()->toDateString(),
            'ends_on' => now()->addWeek()->toDateString(),
        ];
    }

    public function supervisor(): static
    {
        return $this->state(fn (array $attributes): array => ['role' => AssignmentRole::Supervisor]);
    }

    public function inArea(GeoArea $area): static
    {
        return $this->state(fn (array $attributes): array => app(GeoAreaResolver::class)->fullPath($area));
    }
}
