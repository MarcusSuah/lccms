<?php

namespace App\Models\Facilities;

use App\Enums\Facilities\FacilityCategory;
use App\Enums\Facilities\OperatingStatus;
use App\Enums\Facilities\Ownership;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\GeoScoped;
use App\Models\Concerns\TracksAuthors;
use Database\Factories\Facilities\FacilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $reference_code
 * @property string $name
 * @property int $facility_type_id
 * @property FacilityCategory $category
 * @property Ownership $ownership
 * @property OperatingStatus $operating_status
 * @property int $county_id
 * @property int $district_id
 * @property int $clan_id
 * @property int $town_id
 * @property int $community_id
 * @property int|null $block_id
 * @property string|null $address_description
 * @property string|null $latitude
 * @property string|null $longitude
 * @property string|null $contact_name
 * @property string|null $contact_phone
 * @property string|null $contact_email
 * @property int|null $established_year
 * @property array<string, mixed>|null $details
 * @property string|null $notes
 * @property VerificationStatus $verification_status
 * @property bool $is_public
 * @property RecordStatus $status
 */
#[Fillable(['name', 'facility_type_id', 'ownership', 'operating_status', 'address_description', 'latitude', 'longitude', 'contact_name', 'contact_phone', 'contact_email', 'established_year', 'details', 'notes', 'verification_status', 'is_public', 'status'])]
#[UseFactory(FacilityFactory::class)]
class Facility extends Model
{
    use AuditsActivity, BelongsToGeoPath, GeoScoped, HasFactory, TracksAuthors;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'verification_status' => 'unverified',
        'is_public' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => FacilityCategory::class,
            'ownership' => Ownership::class,
            'operating_status' => OperatingStatus::class,
            'verification_status' => VerificationStatus::class,
            'status' => RecordStatus::class,
            'details' => 'array',
            'contact_phone' => 'encrypted',
            'is_public' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return ['contact_phone'];
    }

    /**
     * @return BelongsTo<FacilityType, $this>
     */
    public function type(): BelongsTo
    {
        return $this->belongsTo(FacilityType::class, 'facility_type_id');
    }

    /**
     * @return HasMany<FacilityInspection, $this>
     */
    public function inspections(): HasMany
    {
        return $this->hasMany(FacilityInspection::class);
    }
}
