<?php

namespace App\Http\Resources\Access;

use App\Enums\Access\RoleName;
use App\Models\Access\UserGeoScope;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'is_active' => $this->is_active,
            'email_verified' => $this->email_verified_at !== null,
            'two_factor_enabled' => $this->two_factor_confirmed_at !== null,
            'last_login_at' => $this->last_login_at?->toIso8601String(),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->map(fn ($role): array => [
                'value' => $role->name,
                'label' => RoleName::tryFrom($role->name)?->label() ?? $role->name,
            ])->values()),
            'scopes' => $this->whenLoaded('geoScopes', fn () => $this->geoScopes->map(fn (UserGeoScope $scope): array => [
                'id' => $scope->id,
                'level' => $scope->level()->value,
                'area_id' => $scope->areaId(),
                'label' => $scope->level()->label().': '.($scope->area()?->name ?? 'Unknown'),
                'starts_on' => $scope->starts_on?->toDateString(),
                'ends_on' => $scope->ends_on?->toDateString(),
            ])->values()),
        ];
    }
}
