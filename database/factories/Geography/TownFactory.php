<?php

namespace Database\Factories\Geography;

use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Geography\Clan;
use App\Models\Geography\Town;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development fixtures only; never official administrative data.
 *
 * @extends Factory<Town>
 */
class TownFactory extends Factory
{
    protected $model = Town::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'clan_id' => Clan::factory(),
            'county_id' => fn (array $attributes): int => Clan::query()->findOrFail($attributes['clan_id'])->county_id,
            'district_id' => fn (array $attributes): int => Clan::query()->findOrFail($attributes['clan_id'])->district_id,
            'name' => fake()->unique()->city().' Town '.fake()->unique()->numberBetween(1, 99999),
            'reference_code' => 'DEV-T-'.fake()->unique()->numerify('######'),
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
