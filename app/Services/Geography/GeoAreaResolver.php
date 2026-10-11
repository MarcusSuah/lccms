<?php

namespace App\Services\Geography;

use App\Enums\Geography\GeoLevel;
use App\Models\Geography\GeoArea;

/**
 * Finds administrative areas by level and derives consistent ancestor paths
 * from the database rather than trusting client-supplied ancestor ids.
 */
class GeoAreaResolver
{
    public function find(GeoLevel $level, int $id): ?GeoArea
    {
        $class = $level->modelClass();

        /** @var GeoArea|null $area */
        $area = $class::query()->find($id);

        return $area;
    }

    /**
     * Full six-column path for an area; levels below it are null.
     *
     * @return array<string, int|null>
     */
    public function fullPath(GeoArea $area): array
    {
        $path = [];

        foreach (GeoLevel::cases() as $level) {
            $path[$level->column()] = null;
        }

        return [...$path, ...$area->pathAttributes()];
    }
}
