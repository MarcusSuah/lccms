<?php

namespace App\Models\Census;

use App\Enums\Census\BuildingType;
use App\Enums\Census\Tenure;
use App\Enums\RecordStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\CensusScoped;
use App\Models\Concerns\HasRecordVersion;
use App\Models\Concerns\TracksAuthors;
use Database\Factories\Census\HouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical structure on a block. Campaign-independent.
 *
 * @property int $id
 * @property string $uuid
 * @property string $reference_code
 * @property int $county_id
 * @property int $district_id
 * @property int $clan_id
 * @property int $town_id
 * @property int $community_id
 * @property int $block_id
 * @property string|null $local_house_number
 * @property BuildingType|null $building_type
 * @property Tenure|null $tenure
 * @property int|null $floors
 * @property string|null $latitude
 * @property string|null $longitude
 * @property int|null $gps_accuracy_m
 * @property RecordStatus $status
 * @property string|null $notes
 * @property int $version
 */
#[Fillable(['local_house_number', 'building_type', 'tenure', 'floors', 'latitude', 'longitude', 'gps_accuracy_m', 'notes'])]
#[UseFactory(HouseFactory::class)]
class House extends Model
{
    use AuditsActivity, BelongsToGeoPath, CensusScoped, HasFactory, HasRecordVersion, TracksAuthors;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'building_type' => BuildingType::class,
            'tenure' => Tenure::class,
            'status' => RecordStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    /**
     * House coordinates are sensitive residential locations.
     *
     * @return list<string>
     */
    public function auditRedactedAttributes(): array
    {
        return ['latitude', 'longitude'];
    }

    /**
     * @return HasMany<HouseEnumeration, $this>
     */
    public function enumerations(): HasMany
    {
        return $this->hasMany(HouseEnumeration::class);
    }

    /**
     * @return HasMany<Household, $this>
     */
    public function households(): HasMany
    {
        return $this->hasMany(Household::class);
    }
}
