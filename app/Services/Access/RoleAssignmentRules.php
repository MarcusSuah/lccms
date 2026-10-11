<?php

namespace App\Services\Access;

use App\Enums\Access\Permission;
use App\Enums\Access\RoleName;
use App\Models\User;

/**
 * Decides which roles an administrator may grant, so nobody can create an
 * account more powerful than their own.
 */
class RoleAssignmentRules
{
    /**
     * @return list<RoleName>
     */
    public function assignableBy(User $actor): array
    {
        if ($actor->hasRole(RoleName::SystemSuperAdministrator->value)) {
            return RoleName::cases();
        }

        $restricted = [RoleName::SystemSuperAdministrator];

        if (! $actor->can(Permission::GeoNational->value)) {
            $restricted[] = RoleName::NationalCensusAdministrator;
        }

        return array_values(array_filter(
            RoleName::cases(),
            fn (RoleName $role): bool => ! in_array($role, $restricted, true),
        ));
    }

    /**
     * Whether the actor may manage an account that holds the given roles.
     *
     * @param  list<string>  $targetRoles
     */
    public function canManageHolderOf(User $actor, array $targetRoles): bool
    {
        $assignable = array_map(fn (RoleName $role): string => $role->value, $this->assignableBy($actor));

        return array_diff($targetRoles, $assignable) === [];
    }
}
