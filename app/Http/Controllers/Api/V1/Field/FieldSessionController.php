<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Enums\Census\AssignmentRole;
use App\Http\Controllers\Controller;
use App\Models\Sync\Device;
use App\Services\Census\CensusAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Confirms the device is still trusted and lists the campaigns the enumerator
 * may work on today. The device uses offline_expires_at to lock itself.
 */
class FieldSessionController extends Controller
{
    public function show(Request $request, CensusAccess $access): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');
        $user = $request->user();

        $assignments = $access->currentAssignments($user)
            ->where('role', AssignmentRole::Enumerator)
            ->load('campaign');

        return response()->json([
            'user' => ['id' => $user->id, 'name' => $user->name],
            'device' => [
                'uuid' => $device->uuid,
                'label' => $device->label,
                'offline_expires_at' => $device->offline_expires_at?->toIso8601String(),
            ],
            'campaigns' => $assignments
                ->groupBy('campaign_id')
                ->map(fn ($group) => $group->first()->campaign)
                ->filter(fn ($campaign) => $campaign->status->acceptsFieldData())
                ->map(fn ($campaign): array => [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'reference_code' => $campaign->reference_code,
                    'status' => $campaign->status->value,
                ])
                ->values(),
            'assignment_count' => $assignments->count(),
            'limits' => [
                'max_batch_operations' => config('lccms.offline.max_batch_operations'),
            ],
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
