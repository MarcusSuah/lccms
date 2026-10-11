<?php

namespace App\Models\Sync;

use App\Models\Concerns\AuditsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $label
 * @property string|null $platform
 * @property int|null $token_id
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $last_sync_at
 * @property Carbon|null $offline_expires_at
 * @property Carbon|null $revoked_at
 * @property int|null $revoked_by
 * @property string|null $revoke_reason
 */
#[Fillable(['uuid', 'label', 'platform'])]
class Device extends Model
{
    use AuditsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'offline_expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SyncBatch, $this>
     */
    public function batches(): HasMany
    {
        return $this->hasMany(SyncBatch::class);
    }
}
