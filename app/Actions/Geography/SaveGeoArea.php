<?php

namespace App\Actions\Geography;

use App\Enums\Geography\GeoLevel;
use App\Models\Geography\GeoArea;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Geography\ReferenceCodeGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates one administrative area. On creation the denormalised
 * ancestor path is copied from the parent so it can never be inconsistent.
 */
class SaveGeoArea
{
    public function __construct(
        private GeoAreaResolver $resolver,
        private ReferenceCodeGenerator $codes,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated input
     */
    public function handle(GeoLevel $level, array $data, ?GeoArea $area = null): GeoArea
    {
        return DB::transaction(function () use ($level, $data, $area): GeoArea {
            $attributes = Arr::except($data, ['parent_id', 'confirm_possible_duplicate', 'boundary']);

            if (array_key_exists('boundary', $data)) {
                $attributes['boundary'] = $data['boundary'] === null ? null : json_decode((string) $data['boundary'], true);
            }

            if ($area !== null) {
                if (blank($attributes['reference_code'] ?? null)) {
                    unset($attributes['reference_code']);
                }

                $area->update($attributes);

                return $area;
            }

            $class = $level->modelClass();

            /** @var GeoArea $area */
            $area = new $class;

            if ($level->parent() !== null) {
                $parent = $this->resolver->find($level->parent(), (int) $data['parent_id']);
                $area->forceFill($parent->pathAttributes());

                if (blank($attributes['reference_code'] ?? null)) {
                    $attributes['reference_code'] = $this->codes->nextChildCode($parent);
                }
            }

            $area->fill($attributes)->save();

            return $area;
        });
    }
}
