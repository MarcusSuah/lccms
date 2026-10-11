<?php

namespace App\Models\Infrastructure;

use App\Models\Concerns\AuditsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $project_id
 * @property string $title
 * @property Carbon|null $due_on
 * @property Carbon|null $completed_on
 * @property string|null $notes
 * @property int $sort_order
 */
#[Fillable(['title', 'due_on', 'completed_on', 'notes', 'sort_order'])]
class ProjectMilestone extends Model
{
    use AuditsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'completed_on' => 'date',
        ];
    }

    /**
     * @return BelongsTo<DevelopmentProject, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DevelopmentProject::class, 'project_id');
    }
}
