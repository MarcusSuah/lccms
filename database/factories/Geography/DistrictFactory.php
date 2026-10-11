<?php

namespace Database\Factories\Geography;

use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Geography\County;
use App\Models\Geography\District;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development fixtures only; never official administrative data.
 *
 * @extends Factory<District>
 */
class DistrictFactory extends Factory
{
    protected $model = District::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'county_id' => County::factory(),
            'district_number' => null,
            'name' => 'District #'.fake()->unique()->numberBetween(1, 99999),
            'reference_code' => 'DEV-D-'.fake()->unique()->numerify('######'),
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
