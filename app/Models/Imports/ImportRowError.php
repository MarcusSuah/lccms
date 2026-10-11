<?php

namespace App\Models\Imports;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $import_job_id
 * @property int $row_number
 * @property string|null $column
 * @property string $message
 * @property string $severity
 */
#[Fillable(['row_number', 'column', 'message', 'severity'])]
class ImportRowError extends Model
{
    /**
     * @return BelongsTo<ImportJob, $this>
     */
    public function importJob(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class);
    }
}
