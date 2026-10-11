<?php

namespace App\Models\Census;

use App\Enums\Census\CampaignStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\TracksAuthors;
use App\Models\User;
use Database\Factories\Census\CensusCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference_code
 * @property string $name
 * @property string|null $description
 * @property string|null $instructions
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property Carbon $reference_date
 * @property CampaignStatus $status
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 */
#[Fillable(['reference_code', 'name', 'description', 'instructions', 'start_date', 'end_date', 'reference_date'])]
#[UseFactory(CensusCampaignFactory::class)]
class CensusCampaign extends Model
{
    use AuditsActivity, HasFactory, TracksAuthors;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reference_date' => 'date',
            'status' => CampaignStatus::class,
            'closed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<CampaignScope, $this>
     */
    public function scopes(): HasMany
    {
        return $this->hasMany(CampaignScope::class, 'campaign_id');
    }

    /**
     * @return HasMany<CampaignAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(CampaignAssignment::class, 'campaign_id');
    }

    /**
     * @return HasMany<HouseEnumeration, $this>
     */
    public function houseEnumerations(): HasMany
    {
        return $this->hasMany(HouseEnumeration::class, 'campaign_id');
    }

    /**
     * @return HasMany<Household, $this>
     */
    public function households(): HasMany
    {
        return $this->hasMany(Household::class, 'campaign_id');
    }

    /**
     * @return HasMany<Occupant, $this>
     */
    public function occupants(): HasMany
    {
        return $this->hasMany(Occupant::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Whether a record path lies inside the campaign's geographic coverage.
     *
     * @param  array<string, int|null>  $path
     */
    public function covers(array $path): bool
    {
        return $this->scopes->contains(fn (CampaignScope $scope): bool => $scope->covers($path));
    }
}
