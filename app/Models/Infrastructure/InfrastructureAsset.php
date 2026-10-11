<?php

namespace App\Models\Infrastructure;

use App\Enums\Infrastructure\AssetCondition;
use App\Enums\Infrastructure\Availability;
use App\Enums\Infrastructure\InfrastructureCategory;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\BelongsToGeoPath;
use App\Models\Concerns\GeoScoped;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference_code
 * @property string $name
 * @property InfrastructureCategory $category
 * @property int $community_id
 * @property int|null $block_id
 * @property AssetCondition $condition
 * @property Availability $availability
 * @property string|null $latitude
 * @property string|null $longitude
 * @property Carbon|null $last_inspected_on
 * @property string|null $maintenance_needs
 * @property string|null $responsible_organization
 * @property array<string, mixed>|null $details
 * @property string|null $notes
 * @property VerificationStatus $verification_status
 * @property bool $is_public
 * @property RecordStatus $status
 */
#[Fillable(['name', 'category', 'condition', 'availability', 'latitude', 'longitude', 'last_inspected_on', 'maintenance_needs', 'responsible_organization', 'details', 'notes', 'verification_status', 'is_public', 'status'])]
class InfrastructureAsset extends Model
{
    use AuditsActivity, BelongsToGeoPath, GeoScoped, TracksAuthors;

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
            'category' => InfrastructureCategory::class,
            'condition' => AssetCondition::class,
            'availability' => Availability::class,
            'verification_status' => VerificationStatus::class,
            'status' => RecordStatus::class,
            'last_inspected_on' => 'date',
            'details' => 'array',
            'is_public' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }
}
