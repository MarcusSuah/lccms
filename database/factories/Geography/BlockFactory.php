<?php

namespace Database\Factories\Geography;

use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development fixtures only; never official administrative data.
 *
 * @extends Factory<Block>
 */
class BlockFactory extends Factory
{
    protected $model = Block::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'county_id' => fn (array $attributes): int => Community::query()->findOrFail($attributes['community_id'])->county_id,
            'district_id' => fn (array $attributes): int => Community::query()->findOrFail($attributes['community_id'])->district_id,
            'clan_id' => fn (array $attributes): int => Community::query()->findOrFail($attributes['community_id'])->clan_id,
            'town_id' => fn (array $attributes): int => Community::query()->findOrFail($attributes['community_id'])->town_id,
            'name' => fake()->unique()->city().' Block '.fake()->unique()->numberBetween(1, 99999),
            'reference_code' => 'DEV-B-'.fake()->unique()->numerify('######'),
            'status' => RecordStatus::Active,
            'verification_status' => VerificationStatus::Unverified,
            'is_fixture' => true,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Verified,
        ]);
    }
}
