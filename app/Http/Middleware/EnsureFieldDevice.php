<?php

namespace App\Http\Middleware;

use App\Enums\Access\Permission;
use App\Models\Sync\Device;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Field API requests must use a device-bound token of a device that has not
 * been revoked, held by an account that may still do field work. Every
 * successful contact extends the device's offline window.
 */
class EnsureFieldDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($token === null || ! method_exists($token, 'can') || ! $token->can('sync')) {
            return response()->json(['message' => 'A field device token is required.', 'reason' => 'device_token_required'], 401);
        }

        $device = Device::query()->where('token_id', $token->getKey())->where('user_id', $user->id)->first();

        if ($device === null || $device->isRevoked()) {
            return response()->json(['message' => 'This device has been revoked.', 'reason' => 'device_revoked', 'wipe' => true], 401);
        }

        if (! $user->can(Permission::SyncUse->value)) {
            return response()->json(['message' => 'Your role no longer includes field work.', 'reason' => 'not_permitted'], 403);
        }

        $device->forceFill([
            'last_seen_at' => now(),
            'offline_expires_at' => now()->addHours(config('lccms.offline.offline_session_hours')),
        ])->save();

        $request->attributes->set('device', $device);

        return $next($request);
    }
}
