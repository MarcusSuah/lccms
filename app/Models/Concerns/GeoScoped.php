<?php

namespace App\Models\Concerns;

use App\Enums\Geography\GeoLevel;
use App\Models\User;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Records that carry denormalised geographic ancestor columns and must be
 * restricted to the areas a user is assigned to.
 *
 * Applied explicitly through ->visibleTo($user) rather than as a global scope,
 * so that queue workers and console commands never silently fail open.
 */
trait GeoScoped
{
    /**
     * Map of geographic level to the column that identifies it on this model.
     *
     * @return array<string, string>
     */
    public static function geoColumns(): array
    {
        return collect(GeoLevel::cases())
            ->mapWithKeys(fn (GeoLevel $level): array => [$level->value => $level->column()])
            ->all();
    }

    /**
     * The geographic path of this record, keyed by ancestor column.
     *
     * @return array<string, int|null>
     */
    public function geoPath(): array
    {
        $path = [];

        foreach (static::geoColumns() as $level => $column) {
            $path[GeoLevel::from($level)->column()] = $this->getAttribute($column);
        }

        return $path;
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        app(GeoScope::class)->apply($query, $user);
    }
}
