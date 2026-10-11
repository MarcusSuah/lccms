<?php

namespace App\Models\Complaints;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $complaint_id
 * @property int $slot
 * @property string $disk
 * @property string $path
 * @property string $thumbnail_path
 * @property string $mime
 * @property int $size
 * @property int $width
 * @property int $height
 * @property string $sha256
 * @property string|null $original_name
 */
#[Fillable(['slot', 'disk', 'path', 'thumbnail_path', 'mime', 'size', 'width', 'height', 'sha256', 'original_name'])]
class ComplaintAttachment extends Model
{
    /**
     * @return BelongsTo<Complaint, $this>
     */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
