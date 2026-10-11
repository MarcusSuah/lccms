<?php

namespace App\Models\Complaints;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Internal note, never shown to the complainant.
 *
 * @property int $id
 * @property int $complaint_id
 * @property int $author_id
 * @property string $body
 * @property Carbon $created_at
 */
#[Fillable(['author_id', 'body'])]
class ComplaintNote extends Model
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
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
