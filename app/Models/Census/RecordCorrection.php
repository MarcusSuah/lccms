<?php

namespace App\Models\Census;

use App\Enums\Census\CorrectionStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A requested change to a finalized census record. Applied only after an
 * authorised decision, by someone other than the requester.
 *
 * @property int $id
 * @property string $correctable_type
 * @property int $correctable_id
 * @property int $campaign_id
 * @property array<string, array{from: mixed, to: mixed}> $changes
 * @property string $reason
 * @property CorrectionStatus $status
 * @property string $source
 * @property int $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $applied_at
 */
#[Fillable(['campaign_id', 'changes', 'reason', 'source', 'requested_by'])]
class RecordCorrection extends Model
{
    use AuditsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changes' => 'encrypted:array',
            'status' => CorrectionStatus::class,
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return ['changes'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function correctable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
