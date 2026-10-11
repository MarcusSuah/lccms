<?php

namespace App\Models\Geography;

use App\Enums\Geography\GeoLevel;
use Database\Factories\Geography\ClanFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @mixin GeoArea
 */
#[UseFactory(ClanFactory::class)]
class Clan extends GeoArea
{
    protected $table = 'clans';

    public static function level(): GeoLevel
    {
        return GeoLevel::Clan;
    }

    /**
     * @return HasMany<Town, $this>
     */
    public function towns(): HasMany
    {
        return $this->hasMany(Town::class);
    }
}
