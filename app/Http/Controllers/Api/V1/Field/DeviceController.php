<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Actions\Sync\RegisterDevice;
use App\Enums\Sync\ConflictStatus;
use App\Http\Controllers\Controller;
use App\Models\Sync\Device;
use App\Models\Sync\SyncConflict;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    /**
     * Secure logout from the device: the token stops working immediately.
     */
    public function destroy(Request $request, RegisterDevice $devices): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $devices->revoke($device, $request->user(), 'Signed out on the device');

        return response()->json(['revoked' => true, 'wipe' => true]);
    }

    /**
     * Unauthenticated: tells a device whose token stopped working whether it
     * was revoked and must wipe its local data. The UUID is random and the
     * answer reveals nothing else.
     */
    public function status(string $uuid): JsonResponse
    {
        $device = Device::query()->where('uuid', $uuid)->first();

        return response()->json([
            'revoked' => $device === null || $device->isRevoked(),
        ]);
    }

    /**
     * The enumerator's own unresolved conflicts, without server snapshots.
     */
    public function conflicts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => SyncConflict::query()
                ->where('raised_by', $request->user()->id)
                ->where('status', ConflictStatus::Open)
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (SyncConflict $conflict): array => [
                    'id' => $conflict->id,
                    'entity_type' => $conflict->entity_type,
                    'entity_uuid' => $conflict->entity_uuid,
                    'conflict_class' => $conflict->conflict_class->value,
                    'label' => $conflict->conflict_class->label(),
                    'created_at' => $conflict->created_at?->toIso8601String(),
                ]),
        ]);
    }
}
