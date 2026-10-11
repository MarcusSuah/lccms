<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\Census\CensusAccess;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;

/**
 * Census records are reachable through a user's standing geographic scopes
 * or through an active campaign assignment covering the record's area.
 */
trait CensusScoped
{
    use GeoScoped;

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function accessibleBy(Builder $query, User $user): void
    {
        app(CensusAccess::class)->applyToRecords($query, $user);
    }
}
