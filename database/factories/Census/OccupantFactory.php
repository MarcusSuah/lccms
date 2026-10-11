<?php

namespace Database\Factories\Census;

use App\Enums\Census\RelationshipToHead;
use App\Enums\Census\Sex;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Occupant>
 */
class OccupantFactory extends Factory
{
    protected $model = Occupant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'age_years' => fake()->numberBetween(0, 90),
            'sex' => fake()->randomElement([Sex::Male, Sex::Female]),
            'relationship_to_head' => RelationshipToHead::Child,
            'usual_resident' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Occupant $occupant): void {
            $household = $occupant->household_id !== null
                ? Household::query()->findOrFail($occupant->household_id)
                : Household::factory()->create();

            $occupant->forceFill([
                ...$household->geoPath(),
                'household_id' => $household->id,
                'campaign_id' => $household->campaign_id,
                'uuid' => $occupant->uuid ?? (string) Str::uuid(),
                'reference_code' => $occupant->reference_code ?? 'DEV-P-'.Str::upper(Str::random(14)),
                'enumerator_id' => $occupant->enumerator_id ?? $household->enumerator_id,
                'status' => $occupant->status ?? $household->status,
            ]);
        });
    }

    public function inHousehold(Household $household): static
    {
        return $this->state(fn (array $attributes): array => ['household_id' => $household->id]);
    }

    public function head(): static
    {
        return $this->state(fn (array $attributes): array => [
            'relationship_to_head' => RelationshipToHead::Head,
            'age_years' => fake()->numberBetween(18, 80),
        ]);
    }
}
