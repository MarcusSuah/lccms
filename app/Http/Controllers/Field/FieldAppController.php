<?php

namespace App\Http\Controllers\Field;

use App\Actions\Sync\RegisterDevice;
use App\Enums\Access\Permission;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The offline-capable field application shell and device registration.
 * Registration needs an online, signed-in web session; afterwards the device
 * talks to the versioned API with its own token.
 */
class FieldAppController extends Controller
{
    public function show(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::SyncUse->value), 403);

        return Inertia::render('field/index', [
            'offlineSessionHours' => config('lccms.offline.offline_session_hours'),
        ]);
    }

    public function registerDevice(Request $request, RegisterDevice $register): JsonResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:120'],
        ]);

        $result = $register->handle(
            $request->user(),
            $validated['label'],
            substr((string) $request->userAgent(), 0, 255),
        );

        return response()->json([
            'device_uuid' => $result['device']->uuid,
            'token' => $result['token'],
            'user' => ['id' => $request->user()->id, 'name' => $request->user()->name],
            'offline_expires_at' => $result['device']->offline_expires_at?->toIso8601String(),
            'server_time' => now()->toIso8601String(),
        ], 201);
    }
}
