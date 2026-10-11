<?php

namespace App\Models\Infrastructure;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property Carbon $reported_on
 * @property int $progress_percent
 * @property string $summary
 * @property int $reported_by
 */
#[Fillable(['reported_on', 'progress_percent', 'summary', 'reported_by'])]
class ProjectProgressReport extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reported_on' => 'date'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
