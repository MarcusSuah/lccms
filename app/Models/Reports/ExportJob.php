<?php

namespace App\Models\Reports;

use App\Models\Concerns\AuditsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $uuid
 * @property int $user_id
 * @property string $report
 * @property string $format
 * @property array<string, mixed> $parameters
 * @property string $classification
 * @property string|null $purpose
 * @property string $status
 * @property int|null $row_count
 * @property string|null $file_path
 * @property string|null $error
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property int $download_count
 */
#[Fillable(['uuid', 'user_id', 'report', 'format', 'parameters', 'classification', 'purpose'])]
class ExportJob extends Model
{
    use AuditsActivity, Prunable;

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'queued',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED && $this->file_path !== null && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_QUEUED, self::STATUS_RUNNING], true);
    }

    /**
     * Expired files are deleted; the row stays as the record of the export.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->whereNotNull('file_path')->where('expires_at', '<=', now());
    }

    /**
     * Prunable deletes rows by default; keep the audit row and remove only the file.
     */
    public function prune(): bool
    {
        Storage::disk('local')->delete($this->file_path);

        return $this->forceFill(['file_path' => null])->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function toListArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'report' => $this->report,
            'format' => $this->format,
            'status' => $this->status,
            'classification' => $this->classification,
            'row_count' => $this->row_count,
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'is_downloadable' => $this->isDownloadable(),
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
