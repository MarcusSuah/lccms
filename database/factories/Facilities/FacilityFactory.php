<?php

namespace Database\Factories\Facilities;

use App\Enums\Facilities\FacilityCategory;
use App\Enums\Facilities\OperatingStatus;
use App\Enums\Facilities\Ownership;
use App\Enums\VerificationStatus;
use App\Models\Facilities\Facility;
use App\Models\Facilities\FacilityType;
use App\Models\Geography\Community;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Facility>
 */
class FacilityFactory extends Factory
{
    protected $model = Facility::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' facility',
            'ownership' => Ownership::Public,
            'operating_status' => OperatingStatus::Operational,
            'verification_status' => VerificationStatus::Unverified,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Facility $facility): void {
            if ($facility->facility_type_id === null) {
                $type = FacilityType::query()->firstOrCreate(
                    ['code' => 'test_primary_school'],
                    ['name' => 'Primary school', 'category' => FacilityCategory::School],
                );
                $facility->facility_type_id = $type->id;
            }

            $type = FacilityType::query()->findOrFail($facility->facility_type_id);
            $community = $facility->community_id !== null
                ? Community::query()->findOrFail($facility->community_id)
                : Community::factory()->create();

            $facility->forceFill([
                ...$community->pathAttributes(),
                'category' => $type->category,
                'reference_code' => $facility->reference_code ?? 'DEV-F-'.Str::upper(Str::random(10)),
            ]);
        });
    }

    public function inCommunity(Community $community): static
    {
        return $this->state(fn (array $attributes): array => ['community_id' => $community->id]);
    }

    public function ofCategory(FacilityCategory $category): static
    {
        return $this->state(function (array $attributes) use ($category): array {
            $type = FacilityType::query()->firstOrCreate(
                ['code' => 'test_'.$category->value],
                ['name' => $category->label(), 'category' => $category],
            );

            return ['facility_type_id' => $type->id];
        });
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes): array => ['verification_status' => VerificationStatus::Verified]);
    }
}
