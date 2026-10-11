<?php

namespace App\Models\Census;

use App\Enums\Census\CensusRecordStatus;
use App\Enums\Census\RelationshipToHead;
use App\Enums\Census\SchoolAttendance;
use App\Enums\Census\Sex;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\CensusScoped;
use App\Models\Concerns\HasRecordVersion;
use App\Models\Geography\GeoArea;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\Census\OccupantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One person in one household for one campaign. Sensitive personal data.
 *
 * @property int $id
 * @property string $uuid
 * @property string $reference_code
 * @property int $household_id
 * @property int $campaign_id
 * @property int $county_id
 * @property int $district_id
 * @property int $clan_id
 * @property int $town_id
 * @property int $community_id
 * @property int $block_id
 * @property string $full_name
 * @property string $normalized_name
 * @property Carbon|null $date_of_birth
 * @property int|null $age_years
 * @property bool $age_is_estimated
 * @property Sex $sex
 * @property RelationshipToHead $relationship_to_head
 * @property bool $usual_resident
 * @property SchoolAttendance|null $school_attendance
 * @property CensusRecordStatus $status
 * @property int $enumerator_id
 * @property int $version
 */
#[Fillable(['full_name', 'date_of_birth', 'age_years', 'age_is_estimated', 'sex', 'relationship_to_head', 'usual_resident', 'school_attendance'])]
#[UseFactory(OccupantFactory::class)]
class Occupant extends Model
{
    use AuditsActivity, BelongsToGeoPath, CensusScoped, HasFactory, HasRecordVersion;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'version' => 1,
        'usual_resident' => true,
        'age_is_estimated' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'age_is_estimated' => 'boolean',
            'usual_resident' => 'boolean',
            'sex' => Sex::class,
            'relationship_to_head' => RelationshipToHead::class,
            'school_attendance' => SchoolAttendance::class,
            'status' => CensusRecordStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Occupant $occupant): void {
            $occupant->normalized_name = GeoArea::normalizeName($occupant->full_name);
        });
    }

    /**
     * Personal data never enters the activity log as values.
     *
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return ['full_name', 'normalized_name', 'date_of_birth'];
    }

    /**
     * Age in completed years on the campaign reference date.
     */
    public function ageOn(CarbonInterface $referenceDate): ?int
    {
        if ($this->date_of_birth !== null) {
            return (int) $this->date_of_birth->diffInYears($referenceDate);
        }

        return $this->age_years;
    }

    /**
     * @return BelongsTo<Household, $this>
     */
    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
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
     * @return MorphMany<RecordCorrection, $this>
     */
    public function corrections(): MorphMany
    {
        return $this->morphMany(RecordCorrection::class, 'correctable');
    }
}
