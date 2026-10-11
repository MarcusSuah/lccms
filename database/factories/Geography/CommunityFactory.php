<?php

namespace Database\Factories\Geography;

use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Geography\Community;
use App\Models\Geography\Town;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development fixtures only; never official administrative data.
 *
 * @extends Factory<Community>
 */
class CommunityFactory extends Factory
{
    protected $model = Community::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'town_id' => Town::factory(),
            'county_id' => fn (array $attributes): int => Town::query()->findOrFail($attributes['town_id'])->county_id,
            'district_id' => fn (array $attributes): int => Town::query()->findOrFail($attributes['town_id'])->district_id,
            'clan_id' => fn (array $attributes): int => Town::query()->findOrFail($attributes['town_id'])->clan_id,
            'name' => fake()->unique()->city().' Community '.fake()->unique()->numberBetween(1, 99999),
            'reference_code' => 'DEV-C-'.fake()->unique()->numerify('######'),
            'status' => RecordStatus::Active,
            'verification_status' => VerificationStatus::Unverified,
            'is_fixture' => true,
            'is_published' => false,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'verification_status' => VerificationStatus::Verified,
        ]);
    }

    /**
     * A community whose profile is visible on the public website.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_published' => true,
        ]);
    }
}
