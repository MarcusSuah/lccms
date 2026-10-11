<?php

namespace App\Models\Census;

use App\Enums\Census\AssignmentRole;
use App\Enums\Census\AssignmentStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\GeoScoped;
use App\Models\Concerns\HasAreaPath;
use App\Models\Concerns\TracksAuthors;
use App\Models\User;
use Database\Factories\Census\CampaignAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Authorises one user to enumerate or supervise one area of one campaign
 * for a period of time.
 *
 * @property int $id
 * @property int $campaign_id
 * @property int $user_id
 * @property AssignmentRole $role
 * @property int|null $supervisor_id
 * @property int $county_id
 * @property int|null $district_id
 * @property int|null $clan_id
 * @property int|null $town_id
 * @property int|null $community_id
 * @property int|null $block_id
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property AssignmentStatus $status
 * @property int|null $expected_houses
 * @property string|null $notes
 * @property int|null $reassigned_from_id
 */
#[Fillable(['campaign_id', 'user_id', 'role', 'supervisor_id', 'county_id', 'district_id', 'clan_id', 'town_id', 'community_id', 'block_id', 'starts_on', 'ends_on', 'expected_houses', 'notes', 'reassigned_from_id'])]
#[UseFactory(CampaignAssignmentFactory::class)]
class CampaignAssignment extends Model
{
    use AuditsActivity, GeoScoped, HasAreaPath, HasFactory, TracksAuthors;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => AssignmentRole::class,
            'status' => AssignmentStatus::class,
            'starts_on' => 'date',
            'ends_on' => 'date',
        ];
    }

    /**
     * Active assignments whose date window includes today.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $today = now()->toDateString();

        $query->where('status', AssignmentStatus::Active)
            ->whereDate('starts_on', '<=', $today)
            ->whereDate('ends_on', '>=', $today);
    }

    public function isCurrent(): bool
    {
        $today = now()->startOfDay();

        return $this->status === AssignmentStatus::Active
            && $this->starts_on->lte($today)
            && $this->ends_on->gte($today);
    }

    /**
     * @return BelongsTo<CensusCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(CensusCampaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }
}
