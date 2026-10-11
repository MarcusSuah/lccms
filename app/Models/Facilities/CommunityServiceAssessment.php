<?php

namespace App\Models\Facilities;

use App\Enums\Facilities\ServiceAssessmentStatus;
use App\Enums\Facilities\ServiceCategory;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\GeoScoped;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Records whether a service is present or verified absent in a community, so
 * that "none recorded" is never mistaken for "none exists".
 *
 * @property int $id
 * @property int $community_id
 * @property ServiceCategory $category
 * @property ServiceAssessmentStatus $status
 * @property Carbon $assessed_on
 * @property int $assessed_by
 * @property string|null $notes
 */
#[Fillable(['status', 'assessed_on', 'notes'])]
class CommunityServiceAssessment extends Model
{
    use AuditsActivity, BelongsToGeoPath, GeoScoped;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => ServiceCategory::class,
            'status' => ServiceAssessmentStatus::class,
            'assessed_on' => 'date',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function geoColumns(): array
    {
        return [
            'county' => 'county_id',
            'district' => 'district_id',
            'clan' => 'clan_id',
            'town' => 'town_id',
            'community' => 'community_id',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assessor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assessed_by');
    }
}
