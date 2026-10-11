<?php

namespace App\Policies;

use App\Enums\Access\Permission;
use App\Models\User;
use App\Services\Access\RoleAssignmentRules;
use App\Services\Geography\GeoScope;

class UserPolicy
{
    public function __construct(
        private GeoScope $geoScope,
        private RoleAssignmentRules $roleRules,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $actor->can(Permission::UsersView->value);
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->can(Permission::UsersView->value) && $this->withinReach($actor, $target);
    }

    public function create(User $actor): bool
    {
        return $actor->can(Permission::UsersManage->value);
    }

    /**
     * Administrators manage other accounts only; their own profile is edited in settings.
     */
    public function update(User $actor, User $target): bool
    {
        return $actor->isNot($target)
            && $actor->can(Permission::UsersManage->value)
            && $this->withinReach($actor, $target)
            && $this->roleRules->canManageHolderOf($actor, $target->getRoleNames()->all());
    }

    /**
     * A target is within reach when every one of their geographic scopes lies
     * inside the actor's areas. Accounts without scopes are national-only.
     */
    private function withinReach(User $actor, User $target): bool
    {
        if ($this->geoScope->for($actor)->national) {
            return true;
        }

        $scopes = $target->geoScopes()->get();

        if ($scopes->isEmpty()) {
            return false;
        }

        return $scopes->every(fn ($scope): bool => $this->geoScope->allowsPath($actor, $scope->path()));
    }
}
