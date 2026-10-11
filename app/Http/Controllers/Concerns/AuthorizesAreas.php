<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\Access\Permission;
use App\Models\User;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Permission decides what; geographic reach decides where. A record outside
 * the user's areas answers 404 so its existence is not disclosed.
 */
trait AuthorizesAreas
{
    /**
     * @param  Permission|list<Permission>  $permission  any of the given permissions suffices
     * @param  Model|array<string, int|null>|null  $target  record or path; null checks permission only
     */
    protected function authorizeArea(User $user, Permission|array $permission, Model|array|null $target = null): void
    {
        $permissions = is_array($permission) ? $permission : [$permission];

        abort_unless(
            collect($permissions)->contains(fn (Permission $candidate): bool => $user->can($candidate->value)),
            403,
        );

        if ($target === null) {
            return;
        }

        $geoScope = app(GeoScope::class);
        $allowed = $target instanceof Model ? $geoScope->allows($user, $target) : $geoScope->allowsPath($user, $target);

        abort_unless($allowed, 404);
    }
}
