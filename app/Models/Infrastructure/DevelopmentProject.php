<?php

namespace App\Models\Infrastructure;

use App\Enums\Infrastructure\ProjectCategory;
use App\Enums\Infrastructure\ProjectStatus;
use App\Enums\VerificationStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\GeoScoped;
use App\Models\Concerns\HasAreaPath;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A development project located at any level from county to block.
 *
 * @property int $id
 * @property string $reference_code
 * @property string $title
 * @property string|null $description
 * @property ProjectCategory $category
 * @property ProjectStatus $status
 * @property Carbon|null $start_date
 * @property Carbon|null $target_end_date
 * @property Carbon|null $actual_end_date
 * @property string|null $sponsor
 * @property string|null $responsible_organization
 * @property string|null $budget_amount
 * @property string|null $budget_currency
 * @property bool $budget_is_public
 * @property int $progress_percent
 * @property VerificationStatus $verification_status
 * @property bool $is_public
 */
#[Fillable(['title', 'description', 'category', 'status', 'start_date', 'target_end_date', 'actual_end_date', 'sponsor', 'responsible_organization', 'budget_amount', 'budget_currency', 'budget_is_public', 'progress_percent', 'verification_status', 'is_public'])]
class DevelopmentProject extends Model
{
    use AuditsActivity, GeoScoped, HasAreaPath, TracksAuthors;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'planned',
        'progress_percent' => 0,
        'verification_status' => 'unverified',
        'is_public' => false,
        'budget_is_public' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ProjectCategory::class,
            'status' => ProjectStatus::class,
            'verification_status' => VerificationStatus::class,
            'start_date' => 'date',
            'target_end_date' => 'date',
            'actual_end_date' => 'date',
            'budget_amount' => 'decimal:2',
            'budget_is_public' => 'boolean',
            'is_public' => 'boolean',
        ];
    }

    /**
     * @return HasMany<ProjectMilestone, $this>
     */
    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class, 'project_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<ProjectProgressReport, $this>
     */
    public function progressReports(): HasMany
    {
        return $this->hasMany(ProjectProgressReport::class, 'project_id')->latest('reported_on');
    }
}
