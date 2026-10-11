<?php

namespace Database\Factories\Geography;

use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Geography\County;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Development fixtures only; never official administrative data.
 *
 * @extends Factory<County>
 */
class CountyFactory extends Factory
{
    protected $model = County::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' County '.fake()->unique()->numberBetween(1, 99999),
            'reference_code' => 'DEV-CO-'.fake()->unique()->numerify('######'),
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
