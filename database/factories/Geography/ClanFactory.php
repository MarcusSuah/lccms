<?php

namespace Database\Factories\Geography;

use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Geography\Clan;
use App\Models\Geography\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development fixtures only; never official administrative data.
 *
 * @extends Factory<Clan>
 */
class ClanFactory extends Factory
{
    protected $model = Clan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'district_id' => District::factory(),
            'county_id' => fn (array $attributes): int => District::query()->findOrFail($attributes['district_id'])->county_id,
            'name' => fake()->unique()->city().' Clan '.fake()->unique()->numberBetween(1, 99999),
            'reference_code' => 'DEV-CL-'.fake()->unique()->numerify('######'),
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
