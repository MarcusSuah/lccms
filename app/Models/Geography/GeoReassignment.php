<?php

namespace App\Models\Geography;

use App\Enums\Geography\GeoLevel;
use App\Enums\Geography\ReassignmentStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An audited request to move an area under a different parent.
 *
 * @property int $id
 * @property GeoLevel $level
 * @property int $area_id
 * @property string $area_reference_code
 * @property string $area_name
 * @property int $from_parent_id
 * @property int $to_parent_id
 * @property array<string, int|null> $from_path
 * @property array<string, int|null> $to_path
 * @property string $reason
 * @property Carbon $effective_on
 * @property ReassignmentStatus $status
 * @property int $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property string|null $decision_note
 * @property Carbon|null $applied_at
 * @property array<string, int>|null $affected_counts
 */
#[Fillable(['level', 'area_id', 'area_reference_code', 'area_name', 'from_parent_id', 'to_parent_id', 'from_path', 'to_path', 'reason', 'effective_on', 'requested_by'])]
class GeoReassignment extends Model
{
    use AuditsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => GeoLevel::class,
            'status' => ReassignmentStatus::class,
            'from_path' => 'array',
            'to_path' => 'array',
            'effective_on' => 'date',
            'decided_at' => 'datetime',
            'applied_at' => 'datetime',
            'affected_counts' => 'array',
        ];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

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

    /**
     * @return array<string, mixed>
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'level' => $this->level->value,
            'area_id' => $this->area_id,
            'area_name' => $this->area_name,
            'area_reference_code' => $this->area_reference_code,
            'from_parent_id' => $this->from_parent_id,
            'to_parent_id' => $this->to_parent_id,
            'reason' => $this->reason,
            'effective_on' => $this->effective_on->toDateString(),
            'status' => $this->status->value,
            'requested_by' => $this->requester?->name,
            'decided_by' => $this->decider?->name,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'decision_note' => $this->decision_note,
            'affected_counts' => $this->affected_counts,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
