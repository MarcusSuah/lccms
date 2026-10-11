<?php

namespace Database\Factories\Census;

use App\Models\Census\House;
use App\Models\Geography\Block;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<House>
 */
class HouseFactory extends Factory
{
    protected $model = House::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'local_house_number' => (string) fake()->unique()->numberBetween(1, 999999),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (House $house): void {
            if ($house->block_id === null) {
                $block = Block::factory()->create();
                $house->forceFill($block->pathAttributes());
            }

            $house->uuid ??= (string) Str::uuid();
            $house->reference_code ??= 'DEV-H-'.Str::upper(Str::random(10));
        });
    }

    public function onBlock(Block $block): static
    {
        return $this->state(fn (array $attributes): array => $block->pathAttributes());
    }
}
