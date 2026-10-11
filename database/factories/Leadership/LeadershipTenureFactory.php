<?php

namespace Database\Factories\Leadership;

use App\Enums\Leadership\LeadershipLevel;
use App\Enums\Leadership\SelectionMethod;
use App\Models\Geography\Community;
use App\Models\Leadership\LeadershipPosition;
use App\Models\Leadership\LeadershipTenure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadershipTenure>
 */
class LeadershipTenureFactory extends Factory
{
    protected $model = LeadershipTenure::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'holder_name' => fake()->name(),
            'starts_on' => now()->subYears(2)->toDateString(),
            'ends_on' => null,
            'selection_method' => SelectionMethod::Elected,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (LeadershipTenure $tenure): void {
            $position = $tenure->position_id !== null
                ? LeadershipPosition::query()->findOrFail($tenure->position_id)
                : LeadershipPosition::query()->firstOrCreate(
                    ['code' => 'community_chairperson'],
                    ['title' => 'Community chairperson', 'level' => LeadershipLevel::Community, 'single_holder' => true],
                );
            $community = $tenure->community_id !== null
                ? Community::query()->findOrFail($tenure->community_id)
                : Community::factory()->create();

            $tenure->forceFill([
                ...$community->pathAttributes(),
                'position_id' => $position->id,
                'level' => $position->level,
            ]);
        });
    }

    public function inCommunity(Community $community): static
    {
        return $this->state(fn (array $attributes): array => ['community_id' => $community->id]);
    }
}
