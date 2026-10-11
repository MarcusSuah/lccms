<?php

namespace App\Models\Imports;

use App\Enums\Imports\ImportStatus;
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
 * @property string $type
 * @property string $original_filename
 * @property string $stored_path
 * @property ImportStatus $status
 * @property int $total_rows
 * @property int $valid_rows
 * @property int $invalid_rows
 * @property int $imported_rows
 * @property list<array<string, mixed>>|null $preview
 * @property int $uploaded_by
 * @property Carbon|null $validated_at
 * @property Carbon|null $committed_at
 * @property Carbon|null $created_at
 */
#[Fillable(['uuid', 'type', 'original_filename', 'stored_path', 'uploaded_by'])]
class ImportJob extends Model
{
    use AuditsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'uploaded',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'preview' => 'array',
            'validated_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * @return HasMany<ImportRowError, $this>
     */
    public function rowErrors(): HasMany
    {
        return $this->hasMany(ImportRowError::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
