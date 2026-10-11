<?php

namespace App\Models\Sync;

use App\Models\Census\CensusCampaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $batch_uuid
 * @property int $device_id
 * @property int $user_id
 * @property int $campaign_id
 * @property int $operation_count
 * @property string $status
 * @property array<string, int>|null $summary
 * @property Carbon $received_at
 * @property Carbon|null $finished_at
 */
#[Fillable(['batch_uuid', 'device_id', 'user_id', 'campaign_id', 'operation_count', 'received_at'])]
class SyncBatch extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'received_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<SyncOperation, $this>
     */
    public function operations(): HasMany
    {
        return $this->hasMany(SyncOperation::class, 'batch_id');
    }

    /**
     * @return BelongsTo<Device, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<CensusCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CensusCampaign::class, 'campaign_id');
    }
}
