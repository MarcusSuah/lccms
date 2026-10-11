<?php

namespace App\Models\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\OccupancyStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\CensusScoped;
use App\Models\Concerns\HasRecordVersion;
use App\Models\User;
use Database\Factories\Census\HouseEnumerationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * The visit to one house during one campaign.
 *
 * @property int $id
 * @property string $uuid
 * @property int $house_id
 * @property int $campaign_id
 * @property int $block_id
 * @property int $community_id
 * @property OccupancyStatus $occupancy_status
 * @property CensusRecordStatus $status
 * @property int $enumerator_id
 * @property int|null $supervisor_id
 * @property Carbon|null $visited_at
 * @property string|null $notes
 * @property int $version
 */
#[Fillable(['occupancy_status', 'visited_at', 'notes'])]
#[UseFactory(HouseEnumerationFactory::class)]
class HouseEnumeration extends Model
{
    use AuditsActivity, BelongsToGeoPath, CensusScoped, HasFactory, HasRecordVersion;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occupancy_status' => OccupancyStatus::class,
            'status' => CensusRecordStatus::class,
            'visited_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<House, $this>
     */
    public function house(): BelongsTo
    {
        return $this->belongsTo(House::class);
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
    public function enumerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enumerator_id');
    }

    /**
     * @return MorphMany<RecordReview, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(RecordReview::class, 'reviewable');
    }
}
