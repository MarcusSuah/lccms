<?php

namespace App\Models\Sync;

use App\Enums\Sync\ConflictClass;
use App\Enums\Sync\ConflictStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\GeoScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A device change that could not be applied without overwriting newer server
 * data. Both versions are preserved (encrypted) until an authorised person
 * resolves it.
 *
 * @property int $id
 * @property int $sync_operation_id
 * @property int $campaign_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $entity_uuid
 * @property ConflictClass $conflict_class
 * @property int|null $base_version
 * @property int $server_version
 * @property array<string, mixed> $server_snapshot
 * @property array<string, mixed> $submitted_payload
 * @property ConflictStatus $status
 * @property int $raised_by
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property string|null $resolution_note
 */
#[Fillable(['sync_operation_id', 'campaign_id', 'entity_type', 'entity_id', 'entity_uuid', 'conflict_class', 'base_version', 'server_version', 'server_snapshot', 'submitted_payload', 'raised_by', 'county_id', 'district_id', 'clan_id', 'town_id', 'community_id', 'block_id'])]
class SyncConflict extends Model
{
    use AuditsActivity, GeoScoped;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'conflict_class' => ConflictClass::class,
            'status' => ConflictStatus::class,
            'server_snapshot' => 'encrypted:array',
            'submitted_payload' => 'encrypted:array',
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return ['server_snapshot', 'submitted_payload'];
    }

    /**
     * @return BelongsTo<SyncOperation, $this>
     */
    public function operation(): BelongsTo
    {
        return $this->belongsTo(SyncOperation::class, 'sync_operation_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
