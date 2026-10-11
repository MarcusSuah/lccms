<?php

namespace App\Services\Sync;

use App\Enums\Sync\ConflictClass;
use App\Enums\Sync\SyncOperationStatus;
use App\Exceptions\CensusActionException;
use App\Models\Census\CensusCampaign;
use App\Models\Sync\Device;
use App\Models\Sync\SyncBatch;
use App\Models\Sync\SyncConflict;
use App\Models\Sync\SyncOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Processes a batch of queued device operations.
 *
 * Guarantees:
 *  - Idempotent: an op_uuid is applied at most once. Replays return the stored
 *    result; a reused op_uuid with a different payload is refused.
 *  - Entity-level idempotency: creating an entity whose UUID already exists
 *    returns the existing record instead of a second row.
 *  - Isolation: each operation has its own transaction, so one bad item never
 *    poisons the batch.
 *  - No silent overwrite: stale or post-review edits are preserved as conflicts.
 *  - Transient database failures are reported as retryable.
 */
class SyncBatchProcessor
{
    public function __construct(private SyncOperationHandler $handler) {}

    /**
     * @param  array{batch_uuid: string, campaign_id: int, operations: list<array{op_uuid: string, entity_type: string, entity_uuid: string, operation: string, base_version?: int|null, payload?: array<string, mixed>}>}  $input
     * @return array{batch_uuid: string, server_time: string, results: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function process(User $user, Device $device, CensusCampaign $campaign, array $input): array
    {
        $batch = SyncBatch::query()->firstOrCreate(
            ['device_id' => $device->id, 'batch_uuid' => $input['batch_uuid']],
            [
                'user_id' => $user->id,
                'campaign_id' => $campaign->id,
                'operation_count' => count($input['operations']),
                'received_at' => now(),
            ],
        );

        $results = [];

        foreach ($input['operations'] as $operation) {
            $results[] = $this->processOperation($user, $campaign, $batch, $operation);
        }

        $summary = array_count_values(array_map(fn (array $result): string => $result['status'], $results));

        $batch->forceFill(['status' => 'completed', 'summary' => $summary, 'finished_at' => now()])->save();
        $device->forceFill(['last_sync_at' => now()])->save();

        Log::info('Field sync batch processed', [
            'batch_id' => $batch->id,
            'device_id' => $device->id,
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'summary' => $summary,
        ]);

        return [
            'batch_uuid' => $batch->batch_uuid,
            'server_time' => now()->toIso8601String(),
            'results' => $results,
            'summary' => $summary,
        ];
    }

    /**
     * @param  array{op_uuid: string, entity_type: string, entity_uuid: string, operation: string, base_version?: int|null, payload?: array<string, mixed>}  $input
     * @return array<string, mixed>
     */
    private function processOperation(User $user, CensusCampaign $campaign, SyncBatch $batch, array $input): array
    {
        $payload = $input['payload'] ?? [];
        $baseVersion = isset($input['base_version']) ? (int) $input['base_version'] : null;
        $hash = $this->hash($input['entity_type'], $input['entity_uuid'], $input['operation'], $baseVersion, $payload);

        $stored = SyncOperation::query()->where('op_uuid', $input['op_uuid'])->first();

        if ($stored !== null && $stored->status !== SyncOperationStatus::Retryable) {
            if ($stored->payload_hash !== $hash || $stored->user_id !== $user->id) {
                return [
                    'op_uuid' => $input['op_uuid'],
                    'status' => SyncOperationStatus::Rejected->value,
                    'code' => 'op_uuid_reused',
                    'errors' => ['operation' => ['This operation id was already used for different data.']],
                ];
            }

            return [...$stored->toResult(), 'replayed' => true];
        }

        $operationRow = $stored ?? new SyncOperation([
            'op_uuid' => $input['op_uuid'],
            'batch_id' => $batch->id,
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'entity_type' => $input['entity_type'],
            'entity_uuid' => $input['entity_uuid'],
            'operation' => $input['operation'],
            'base_version' => $baseVersion,
            'payload' => $payload,
            'payload_hash' => $hash,
        ]);

        try {
            return DB::transaction(function () use ($user, $campaign, $input, $baseVersion, $payload, $operationRow): array {
                $outcome = $this->applyIdempotently($user, $campaign, $input, $baseVersion, $payload);

                return $this->persist($operationRow, $outcome)->toResult();
            });
        } catch (SyncConflictException $conflict) {
            return $this->preserveConflict($user, $campaign, $operationRow, $conflict->conflictClass, $conflict->record, $payload)->toResult();
        } catch (ValidationException $exception) {
            return $this->persist($operationRow, SyncOutcome::rejected('validation_failed', $exception->errors()))->toResult();
        } catch (CensusActionException $exception) {
            $record = $this->handler->existing($input['entity_type'], $input['entity_uuid']);

            if ($exception->reason === CensusActionException::UNAUTHORIZED
                && $input['operation'] !== 'create'
                && $record !== null
                && (int) $record->getAttribute('enumerator_id') === $user->id) {
                return $this->preserveConflict($user, $campaign, $operationRow, ConflictClass::ScopeChanged, $record, $payload)->toResult();
            }

            return $this->persist($operationRow, SyncOutcome::rejected($exception->reason, ['operation' => [$exception->getMessage()]]))->toResult();
        } catch (ParentPendingException $exception) {
            return $this->persist($operationRow, SyncOutcome::retryable('parent_pending', $exception->getMessage()))->toResult();
        } catch (ModelNotFoundException) {
            return $this->persist($operationRow, SyncOutcome::retryable('entity_pending', 'The record has not been received yet.'))->toResult();
        } catch (QueryException $exception) {
            if ($this->isTransient($exception)) {
                return $this->persist($operationRow, SyncOutcome::retryable('busy', 'The server was busy. The device will retry.'))->toResult();
            }

            report($exception);

            return $this->persist($operationRow, SyncOutcome::rejected('server_error', ['operation' => ['The server could not store this record.']]))->toResult();
        } catch (Throwable $exception) {
            report($exception);

            return $this->persist($operationRow, SyncOutcome::retryable('server_error', 'Unexpected server error. The device will retry.'))->toResult();
        }
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $payload
     */
    private function applyIdempotently(User $user, CensusCampaign $campaign, array $input, ?int $baseVersion, array $payload): SyncOutcome
    {
        $entityType = $input['entity_type'];
        $operation = $input['operation'];

        if (! in_array($operation, SyncOperationHandler::OPERATIONS[$entityType] ?? [], true)) {
            return SyncOutcome::rejected('unsupported_operation', ['operation' => ["Unsupported operation {$entityType}.{$operation}."]]);
        }

        if ($operation === 'create') {
            $existing = $this->handler->existing($entityType, $input['entity_uuid']);

            if ($existing !== null) {
                $owner = (int) ($existing->getAttribute('enumerator_id') ?? $existing->getAttribute('created_by'));

                return $owner === $user->id
                    ? new SyncOutcome(SyncOperationStatus::Duplicate, SyncOperationHandler::summarize($existing))
                    : SyncOutcome::rejected('uuid_conflict', ['entity_uuid' => ['This record identifier belongs to someone else.']]);
            }
        }

        return SyncOutcome::applied(
            $this->handler->apply($user, $campaign, $entityType, $operation, $input['entity_uuid'], $baseVersion, $payload),
        );
    }

    private function persist(SyncOperation $operation, SyncOutcome $outcome): SyncOperation
    {
        $operation->forceFill([
            'status' => $outcome->status,
            'error_code' => $outcome->code,
            'errors' => $outcome->errors,
            'result' => $outcome->record,
            'conflict_id' => $outcome->conflictId,
            'processed_at' => now(),
        ])->save();

        return $operation;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function preserveConflict(User $user, CensusCampaign $campaign, SyncOperation $operation, ConflictClass $class, Model $record, array $payload): SyncOperation
    {
        return DB::transaction(function () use ($user, $campaign, $operation, $class, $record, $payload): SyncOperation {
            $record->refresh();
            $this->persist($operation, new SyncOutcome(SyncOperationStatus::Conflict, SyncOperationHandler::summarize($record), $class->value));

            $path = method_exists($record, 'geoPath') ? $record->geoPath() : [];

            $conflict = SyncConflict::query()->create([
                ...$path,
                'sync_operation_id' => $operation->id,
                'campaign_id' => $campaign->id,
                'entity_type' => $operation->entity_type,
                'entity_id' => $record->getKey(),
                'entity_uuid' => $operation->entity_uuid,
                'conflict_class' => $class,
                'base_version' => $operation->base_version,
                'server_version' => (int) $record->getAttribute('version'),
                'server_snapshot' => $record->only(array_merge($record->getFillable(), ['status', 'version'])),
                'submitted_payload' => $payload,
                'raised_by' => $user->id,
            ]);

            $operation->forceFill(['conflict_id' => $conflict->id])->save();

            return $operation;
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(string $entityType, string $entityUuid, string $operation, ?int $baseVersion, array $payload): string
    {
        return hash('sha256', json_encode([
            $entityType, $entityUuid, $operation, $baseVersion, $this->canonical($payload),
        ], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonical($item), $value);
    }

    private function isTransient(QueryException $exception): bool
    {
        $code = (int) ($exception->errorInfo[1] ?? 0);

        return in_array($code, [1205, 1213], true);
    }
}
