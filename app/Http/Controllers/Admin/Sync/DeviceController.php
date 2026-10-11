<?php

namespace App\Http\Controllers\Admin\Sync;

use App\Actions\Sync\RegisterDevice;
use App\Enums\Access\Permission;
use App\Http\Controllers\Controller;
use App\Models\Sync\Device;
use App\Models\Sync\SyncBatch;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Registered field devices and their synchronisation health. Revoking a
 * device stops its token immediately and instructs it to wipe local data at
 * its next contact.
 */
class DeviceController extends Controller
{
    public function index(Request $request, GeoScope $geoScope): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::DevicesManage->value), 403);

        $devices = Device::query()
            ->when(! $geoScope->for($user)->national, fn (Builder $query) => $query->whereHas(
                'user.geoScopes',
                fn (Builder $scopes) => $geoScope->apply($scopes, $user),
            ))
            ->with('user:id,name,email')
            ->withCount(['batches'])
            ->orderByRaw('revoked_at IS NOT NULL')
            ->latest('last_seen_at')
            ->paginate(25)
            ->through(fn (Device $device): array => [
                'id' => $device->id,
                'label' => $device->label,
                'user' => $device->user?->only(['id', 'name', 'email']),
                'platform' => $device->platform,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'last_sync_at' => $device->last_sync_at?->toIso8601String(),
                'offline_expires_at' => $device->offline_expires_at?->toIso8601String(),
                'revoked_at' => $device->revoked_at?->toIso8601String(),
                'revoke_reason' => $device->revoke_reason,
                'batches_count' => $device->batches_count,
                'last_summary' => SyncBatch::query()->where('device_id', $device->id)->latest('id')->value('summary'),
            ]);

        return Inertia::render('admin/sync/devices', ['devices' => $devices]);
    }

    public function destroy(Request $request, Device $device, RegisterDevice $devices, GeoScope $geoScope): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can(Permission::DevicesManage->value), 403);

        $owner = $device->user;
        $inReach = $geoScope->for($user)->national
            || $owner->geoScopes()->get()->contains(fn ($scope): bool => $geoScope->allowsPath($user, $scope->path()));
        abort_unless($inReach, 404);

        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:255']]);

        $devices->revoke($device, $user, $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Device revoked. It will wipe its local data at next contact.')]);

        return back();
    }
}
