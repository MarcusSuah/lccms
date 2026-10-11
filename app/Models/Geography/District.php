<?php

namespace App\Models\Geography;

use App\Enums\Geography\GeoLevel;
use Database\Factories\Geography\DistrictFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int|null $district_number
 */
#[UseFactory(DistrictFactory::class)]
class District extends GeoArea
{
    protected $table = 'districts';

    public static function level(): GeoLevel
    {
        return GeoLevel::District;
    }

    protected function levelFillable(): array
    {
        return ['district_number'];
    }

    /**
     * @return HasMany<Clan, $this>
     */
    public function clans(): HasMany
    {
        return $this->hasMany(Clan::class);
    }
}
