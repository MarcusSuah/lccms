<?php

namespace App\Models\Geography;

use App\Enums\Geography\GeoLevel;
use Database\Factories\Geography\CountyFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin GeoArea
 */
#[UseFactory(CountyFactory::class)]
class County extends GeoArea
{
    protected $table = 'counties';

    public static function level(): GeoLevel
    {
        return GeoLevel::County;
    }

    /**
     * @return HasMany<District, $this>
     */
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
