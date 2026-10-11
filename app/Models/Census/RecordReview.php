<?php

namespace App\Models\Census;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * Append-only workflow history.
 *
 * @property int $id
 * @property string $reviewable_type
 * @property int $reviewable_id
 * @property int $campaign_id
 * @property string $action
 * @property string|null $from_status
 * @property string $to_status
 * @property string|null $note
 * @property int $actor_id
 * @property Carbon $created_at
 */
#[Fillable(['campaign_id', 'action', 'from_status', 'to_status', 'note', 'actor_id'])]
class RecordReview extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
