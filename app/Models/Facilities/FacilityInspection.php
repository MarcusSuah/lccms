<?php

namespace App\Models\Facilities;

use App\Enums\Facilities\InspectionCondition;
use App\Enums\Facilities\OperatingStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $facility_id
 * @property Carbon $inspected_on
 * @property int $inspector_id
 * @property OperatingStatus $operating_status
 * @property InspectionCondition $condition
 * @property string $findings
 * @property string|null $recommendations
 * @property Carbon|null $follow_up_due_on
 */
#[Fillable(['inspected_on', 'operating_status', 'condition', 'findings', 'recommendations', 'follow_up_due_on'])]
class FacilityInspection extends Model
{
    use AuditsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'inspected_on' => 'date',
            'follow_up_due_on' => 'date',
            'operating_status' => OperatingStatus::class,
            'condition' => InspectionCondition::class,
        ];
    }

    /**
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }
}
