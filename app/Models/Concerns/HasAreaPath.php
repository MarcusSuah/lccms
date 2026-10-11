<?php

namespace App\Models\Concerns;

use App\Enums\Geography\GeoLevel;
use App\Models\Geography\GeoArea;

/**
 * For rows that describe one area with six path columns, where the deepest
 * non-null column is the area itself (staff scopes, campaign scopes and
 * campaign assignments).
 */
trait HasAreaPath
{
    use BelongsToGeoPath;

    public function level(): GeoLevel
    {
        $deepest = GeoLevel::County;

        foreach (GeoLevel::cases() as $level) {
            if ($this->getAttribute($level->column()) !== null) {
                $deepest = $level;
            }
        }

        return $deepest;
    }

    public function areaId(): int
    {
        return (int) $this->getAttribute($this->level()->column());
    }

    /**
     * @return array<string, int|null>
     */
    public function path(): array
    {
        $path = [];

        foreach (GeoLevel::cases() as $level) {
            $path[$level->column()] = $this->getAttribute($level->column());
        }

        return $path;
    }

    /**
     * Whether a record with the given path lies inside this area.
     *
     * @param  array<string, int|string|null>  $path
     */
    public function covers(array $path): bool
    {
        $value = $path[$this->level()->column()] ?? null;

        return $value !== null && (int) $value === $this->areaId();
    }

    public function area(): ?GeoArea
    {
        /** @var GeoArea|null $area */
        $area = $this->getRelationValue($this->level()->value);

        return $area;
    }

    public function areaLabel(): string
    {
        return $this->level()->label().': '.($this->area()?->name ?? 'Unknown');
    }

    /**
     * Relations to eager load before calling area() or areaLabel().
     *
     * @return list<string>
     */
    public static function areaRelations(): array
    {
        return array_map(fn (GeoLevel $level): string => $level->value, GeoLevel::cases());
    }
}
