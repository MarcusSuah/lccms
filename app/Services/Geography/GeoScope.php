<?php

namespace App\Services\Geography;

use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Models\Access\UserGeoScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The single source of truth for geographic access restrictions.
 *
 * Role permissions decide *what* a user may do; this service decides *where*.
 * Both checks must pass. Bound as a scoped singleton so a user's scopes are
 * loaded once per request or job.
 */
class GeoScope
{
    /**
     * @var array<int, ScopeSet>
     */
    private array $resolved = [];

    public function for(User $user): ScopeSet
    {
        return $this->resolved[$user->id] ??= $this->resolve($user);
    }

    public function forget(User $user): void
    {
        unset($this->resolved[$user->id]);
    }

    /**
     * Restrict a query on a geo-scoped model to the user's areas.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function apply(Builder $query, User $user): Builder
    {
        $scopes = $this->for($user);

        if ($scopes->national) {
            return $query;
        }

        $model = $query->getModel();

        /** @var array<string, string> $columns */
        $columns = method_exists($model, 'geoColumns') ? $model::geoColumns() : [];

        $clauses = [];

        foreach ($scopes->areas as $area) {
            $column = $columns[$area['level']->value] ?? null;

            if ($column !== null) {
                $clauses[$model->qualifyColumn($column)][] = $area['id'];
            }
        }

        if ($clauses === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($clauses): void {
            foreach ($clauses as $column => $ids) {
                $inner->orWhereIn($column, array_values(array_unique($ids)));
            }
        });
    }

    /**
     * Restrict a query on one administrative level so a user only sees the
     * areas on the way down to their assignments, and everything beneath them.
     * Used by cascading selectors and area pickers.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyToLevel(Builder $query, GeoLevel $level, User $user): Builder
    {
        $scopes = $this->for($user);

        if ($scopes->national) {
            return $query;
        }

        if ($scopes->areas === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $inner) use ($scopes, $level, $query): void {
            foreach ($scopes->areas as $area) {
                $scopeLevel = $area['level'];

                if ($level->isAbove($scopeLevel)) {
                    $inner->orWhere($query->getModel()->qualifyColumn('id'), $area['path'][$level->column()]);
                } elseif ($level === $scopeLevel) {
                    $inner->orWhere($query->getModel()->qualifyColumn('id'), $area['id']);
                } else {
                    $inner->orWhere($query->getModel()->qualifyColumn($scopeLevel->column()), $area['id']);
                }
            }
        });
    }

    /**
     * Whether a record with the given geographic path is inside the user's areas.
     *
     * @param  array<string, int|string|null>  $path  keyed by level column (county_id, ...)
     */
    public function allowsPath(User $user, array $path): bool
    {
        $scopes = $this->for($user);

        if ($scopes->national) {
            return true;
        }

        foreach ($scopes->areas as $area) {
            $value = $path[$area['level']->column()] ?? null;

            if ($value !== null && (int) $value === $area['id']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a geo-scoped model is inside the user's areas.
     */
    public function allows(User $user, Model $record): bool
    {
        if (! method_exists($record, 'geoColumns')) {
            return false;
        }

        $path = [];

        foreach ($record::geoColumns() as $level => $column) {
            $path[GeoLevel::from($level)->column()] = $record->getAttribute($column);
        }

        return $this->allowsPath($user, $path);
    }

    private function resolve(User $user): ScopeSet
    {
        if ($user->can(Permission::GeoNational->value)) {
            return new ScopeSet(national: true, areas: []);
        }

        $areas = $user->geoScopes()
            ->current()
            ->get()
            ->map(fn (UserGeoScope $scope): array => [
                'level' => $scope->level(),
                'id' => $scope->areaId(),
                'path' => $scope->path(),
            ])
            ->values()
            ->all();

        return new ScopeSet(national: false, areas: $areas);
    }
}
