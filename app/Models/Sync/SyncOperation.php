<?php

namespace App\Models\Sync;

use App\Enums\Sync\SyncOperationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One queued change received from a device. The payload may contain personal
 * data and is stored encrypted.
 *
 * @property int $id
 * @property string $op_uuid
 * @property int $batch_id
 * @property int $user_id
 * @property int $campaign_id
 * @property string $entity_type
 * @property string $entity_uuid
 * @property string $operation
 * @property int|null $base_version
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property SyncOperationStatus $status
 * @property string|null $error_code
 * @property array<string, mixed>|null $errors
 * @property array<string, mixed>|null $result
 * @property int|null $conflict_id
 * @property Carbon|null $processed_at
 */
#[Fillable(['op_uuid', 'batch_id', 'user_id', 'campaign_id', 'entity_type', 'entity_uuid', 'operation', 'base_version', 'payload', 'payload_hash', 'status'])]
#[Hidden(['payload'])]
class SyncOperation extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'encrypted:array',
            'status' => SyncOperationStatus::class,
            'errors' => 'array',
            'result' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * The response entry returned to the device for this operation.
     *
     * @return array<string, mixed>
     */
    public function toResult(): array
    {
        return array_filter([
            'op_uuid' => $this->op_uuid,
            'entity_type' => $this->entity_type,
            'entity_uuid' => $this->entity_uuid,
            'status' => $this->status->value,
            'code' => $this->error_code,
            'errors' => $this->errors,
            'record' => $this->result,
            'conflict_id' => $this->conflict_id,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return BelongsTo<SyncBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(SyncBatch::class, 'batch_id');
    }
}
