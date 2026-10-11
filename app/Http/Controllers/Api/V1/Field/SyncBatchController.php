<?php

namespace App\Http\Controllers\Api\V1\Field;

use App\Http\Controllers\Controller;
use App\Models\Census\CensusCampaign;
use App\Models\Sync\Device;
use App\Models\Sync\SyncBatch;
use App\Models\Sync\SyncOperation;
use App\Services\Sync\SyncBatchProcessor;
use App\Services\Sync\SyncOperationHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Receives queued offline operations. Partial success is normal: the batch
 * returns 200 with a result per operation. Only batch-level problems
 * (authentication, size, malformed envelope) produce an error status.
 */
class SyncBatchController extends Controller
{
    public function store(Request $request, SyncBatchProcessor $processor): JsonResponse
    {
        $maxBytes = config('lccms.offline.max_payload_kilobytes') * 1024;

        if (strlen((string) $request->getContent()) > $maxBytes) {
            return response()->json(['message' => 'The batch is too large. Send fewer operations at a time.', 'reason' => 'batch_too_large'], 413);
        }

        $validated = $request->validate([
            'batch_uuid' => ['required', 'uuid'],
            'campaign_id' => ['required', 'integer', Rule::exists('census_campaigns', 'id')],
            'operations' => ['required', 'array', 'min:1', 'max:'.config('lccms.offline.max_batch_operations')],
            'operations.*.op_uuid' => ['required', 'uuid', 'distinct'],
            'operations.*.entity_type' => ['required', Rule::in(array_keys(SyncOperationHandler::OPERATIONS))],
            'operations.*.entity_uuid' => ['required', 'uuid'],
            'operations.*.operation' => ['required', 'string', 'max:20'],
            'operations.*.base_version' => ['nullable', 'integer', 'min:1'],
            'operations.*.payload' => ['present', 'array'],
        ]);

        /** @var Device $device */
        $device = $request->attributes->get('device');
        $campaign = CensusCampaign::query()->findOrFail($validated['campaign_id']);

        return response()->json($processor->process($request->user(), $device, $campaign, $validated));
    }

    /**
     * Lets a device recover results after a dropped response.
     */
    public function show(Request $request, string $batchUuid): JsonResponse
    {
        /** @var Device $device */
        $device = $request->attributes->get('device');

        $batch = SyncBatch::query()
            ->where('device_id', $device->id)
            ->where('batch_uuid', $batchUuid)
            ->firstOrFail();

        return response()->json([
            'batch_uuid' => $batch->batch_uuid,
            'status' => $batch->status,
            'summary' => $batch->summary,
            'results' => $batch->operations()->orderBy('id')->get()->map(fn (SyncOperation $operation): array => $operation->toResult()),
        ]);
    }
}
