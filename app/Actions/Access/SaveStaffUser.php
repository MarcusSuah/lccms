<?php

namespace App\Actions\Access;

use App\Enums\Geography\GeoLevel;
use App\Models\User;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Geography\GeoScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Creates or updates a staff account with its roles and geographic scopes in
 * one transaction. New accounts receive a password set-up link by email.
 */
class SaveStaffUser
{
    public function __construct(
        private GeoAreaResolver $resolver,
        private GeoScope $geoScope,
    ) {}

    /**
     * @param  array{name: string, email: string, phone?: string|null, is_active?: bool, roles: list<string>, scopes?: list<array{level: string, area_id: int, starts_on?: string|null, ends_on?: string|null}>}  $data
     */
    public function handle(User $actor, array $data, ?User $user = null): User
    {
        $isNew = $user === null;

        $user = DB::transaction(function () use ($actor, $data, $user): User {
            $user ??= new User(['password' => Str::password(40)]);

            $user->fill([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ]);

            if (! $user->exists) {
                $user->created_by = $actor->id;
                $user->email_verified_at = null;
            }

            if (array_key_exists('is_active', $data) && $data['is_active'] !== $user->is_active) {
                $user->is_active = $data['is_active'];
                $user->deactivated_at = $data['is_active'] ? null : now();
            }

            $user->save();
            $user->syncRoles($data['roles']);

            $user->geoScopes()->delete();

            foreach ($data['scopes'] ?? [] as $scope) {
                $area = $this->resolver->find(GeoLevel::from($scope['level']), (int) $scope['area_id']);

                $user->geoScopes()->create([
                    ...$this->resolver->fullPath($area),
                    'starts_on' => $scope['starts_on'] ?? null,
                    'ends_on' => $scope['ends_on'] ?? null,
                    'granted_by' => $actor->id,
                ]);
            }

            if (! $user->is_active) {
                $user->tokens()->delete();
            }

            return $user;
        });

        $this->geoScope->forget($user);

        if ($isNew) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return $user;
    }
}
