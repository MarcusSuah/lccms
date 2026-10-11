<?php

namespace App\Policies;

use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Models\Geography\GeoArea;
use App\Models\User;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Geography\GeoScope;

/**
 * Geographic registry records are not personal data, so any holder of
 * geography.view may browse them. Changes are limited to the user's areas.
 */
class GeoAreaPolicy
{
    public function __construct(
        private GeoScope $geoScope,
        private GeoAreaResolver $resolver,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can(Permission::GeographyView->value);
    }

    public function view(User $user, GeoArea $area): bool
    {
        return $user->can(Permission::GeographyView->value);
    }

    /**
     * Creating a county requires national reach; other levels require reach over the parent.
     */
    public function createUnder(User $user, GeoLevel $level, ?GeoArea $parent): bool
    {
        if (! $user->can(Permission::GeographyManage->value)) {
            return false;
        }

        if ($level === GeoLevel::County || $parent === null) {
            return $this->geoScope->for($user)->national;
        }

        return $this->geoScope->allowsPath($user, $this->resolver->fullPath($parent));
    }

    public function update(User $user, GeoArea $area): bool
    {
        return $user->can(Permission::GeographyManage->value)
            && $this->geoScope->allowsPath($user, $this->resolver->fullPath($area));
    }

    public function requestReassignment(User $user, GeoArea $area): bool
    {
        return $area::level() !== GeoLevel::County
            && $user->can(Permission::GeographyReassign->value)
            && $this->geoScope->allowsPath($user, $this->resolver->fullPath($area));
    }
}
