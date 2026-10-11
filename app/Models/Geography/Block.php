<?php

namespace App\Models\Geography;

use App\Enums\Geography\GeoLevel;
use Database\Factories\Geography\BlockFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;

/**
 * @mixin GeoArea
 */
#[UseFactory(BlockFactory::class)]
class Block extends GeoArea
{
    protected $table = 'blocks';

    public static function level(): GeoLevel
    {
        return GeoLevel::Block;
    }
}
