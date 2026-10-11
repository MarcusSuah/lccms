<?php

namespace App\Models\Census;

use App\Enums\Census\DuplicateStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $occupant_id
 * @property int $matched_occupant_id
 * @property int $score
 * @property list<string> $reasons
 * @property DuplicateStatus $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_note
 */
#[Fillable(['campaign_id', 'occupant_id', 'matched_occupant_id', 'score', 'reasons'])]
class DuplicateFlag extends Model
{
    use AuditsActivity;

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
            'reasons' => 'array',
            'status' => DuplicateStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Occupant, $this>
     */
    public function occupant(): BelongsTo
    {
        return $this->belongsTo(Occupant::class);
    }

    /**
     * @return BelongsTo<Occupant, $this>
     */
    public function matchedOccupant(): BelongsTo
    {
        return $this->belongsTo(Occupant::class, 'matched_occupant_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
