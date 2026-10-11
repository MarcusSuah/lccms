<?php

namespace App\Models\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\RelationshipToHead;
use App\Enums\Census\ResidenceType;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\CensusScoped;
use App\Models\Concerns\HasRecordVersion;
use App\Models\User;
use Database\Factories\Census\HouseholdFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * A social unit living in one house, recorded for one campaign. Its size is
 * always derived from its occupant rows.
 *
 * @property int $id
 * @property string $uuid
 * @property string $reference_code
 * @property int $house_id
 * @property int $campaign_id
 * @property int $county_id
 * @property int $district_id
 * @property int $clan_id
 * @property int $town_id
 * @property int $community_id
 * @property int $block_id
 * @property int $sequence_in_house
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property ResidenceType $residence_type
 * @property CensusRecordStatus $status
 * @property int $enumerator_id
 * @property int|null $supervisor_id
 * @property Carbon|null $submitted_at
 * @property Carbon|null $verified_at
 * @property int|null $verified_by
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property string|null $review_note
 * @property int $version
 */
#[Fillable(['contact_name', 'contact_phone', 'residence_type'])]
#[UseFactory(HouseholdFactory::class)]
class Household extends Model
{
    use AuditsActivity, BelongsToGeoPath, CensusScoped, HasFactory, HasRecordVersion;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'residence_type' => 'usual',
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contact_phone' => 'encrypted',
            'residence_type' => ResidenceType::class,
            'status' => CensusRecordStatus::class,
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return ['contact_name', 'contact_phone'];
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
     * @return HasMany<Occupant, $this>
     */
    public function occupants(): HasMany
    {
        return $this->hasMany(Occupant::class);
    }

    /**
     * @return HasOne<Occupant, $this>
     */
    public function head(): HasOne
    {
        return $this->hasOne(Occupant::class)
            ->where('relationship_to_head', RelationshipToHead::Head)
            ->where('status', '!=', CensusRecordStatus::Archived);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function enumerator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enumerator_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    /**
     * @return MorphMany<RecordReview, $this>
     */
    public function reviews(): MorphMany
    {
        return $this->morphMany(RecordReview::class, 'reviewable');
    }

    /**
     * @return MorphMany<RecordCorrection, $this>
     */
    public function corrections(): MorphMany
    {
        return $this->morphMany(RecordCorrection::class, 'correctable');
    }
}
