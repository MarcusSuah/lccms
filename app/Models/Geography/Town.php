<?php

namespace App\Models\Geography;

use App\Enums\Geography\GeoLevel;
use Database\Factories\Geography\TownFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin GeoArea
 */
#[UseFactory(TownFactory::class)]
class Town extends GeoArea
{
    protected $table = 'towns';

    public static function level(): GeoLevel
    {
        return GeoLevel::Town;
    }

    /**
     * @return HasMany<Community, $this>
     */
    public function communities(): HasMany
    {
        return $this->hasMany(Community::class);
    }
}
