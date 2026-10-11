<?php

namespace App\Actions\Sync;

use App\Enums\Access\Permission;
use App\Models\Sync\Device;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Registers a field device for an enumerator and issues its device-bound
 * API token. The plain-text token is returned once; the device stores it
 * encrypted under the enumerator's offline PIN.
 */
class RegisterDevice
{
    /**
     * @return array{device: Device, token: string}
     */
    public function handle(User $user, string $label, ?string $platform): array
    {
        if (! $user->can(Permission::SyncUse->value)) {
            throw ValidationException::withMessages(['device' => 'Your role does not include offline field work.']);
        }

        $active = Device::query()->where('user_id', $user->id)->whereNull('revoked_at')->count();

        if ($active >= config('lccms.offline.max_devices_per_user')) {
            throw ValidationException::withMessages(['device' => 'You already have the maximum number of registered devices. Ask a supervisor to revoke an old one.']);
        }

        return DB::transaction(function () use ($user, $label, $platform): array {
            $device = new Device(['uuid' => (string) Str::uuid(), 'label' => $label, 'platform' => $platform]);
            $device->user_id = $user->id;

            $token = $user->createToken(
                'device:'.$device->uuid,
                ['sync'],
                now()->addDays(config('lccms.offline.token_lifetime_days')),
            );

            $device->forceFill([
                'token_id' => $token->accessToken->id,
                'last_seen_at' => now(),
                'offline_expires_at' => now()->addHours(config('lccms.offline.offline_session_hours')),
            ])->save();

            return ['device' => $device, 'token' => $token->plainTextToken];
        });
    }

    public function revoke(Device $device, ?User $by, string $reason): void
    {
        DB::transaction(function () use ($device, $by, $reason): void {
            if ($device->token_id !== null) {
                $device->user->tokens()->whereKey($device->token_id)->delete();
            }

            $device->forceFill([
                'revoked_at' => now(),
                'revoked_by' => $by?->id,
                'revoke_reason' => $reason,
                'token_id' => null,
            ])->save();
        });
    }
}
