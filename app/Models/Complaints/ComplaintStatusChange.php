<?php

namespace App\Models\Complaints;

use App\Enums\Complaints\ComplaintStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Status history. public_response, when present, is shown to the complainant.
 *
 * @property int $id
 * @property int $complaint_id
 * @property ComplaintStatus|null $from_status
 * @property ComplaintStatus $to_status
 * @property string|null $public_response
 * @property int|null $actor_id
 * @property Carbon $created_at
 */
#[Table('complaint_status_history')]
#[Fillable(['from_status', 'to_status', 'public_response', 'actor_id'])]
class ComplaintStatusChange extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ComplaintStatus::class,
            'to_status' => ComplaintStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
