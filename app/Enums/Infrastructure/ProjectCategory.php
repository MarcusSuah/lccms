<?php

namespace App\Enums\Infrastructure;

use App\Enums\Concerns\HasOptions;

enum ProjectCategory: string
{
    use HasOptions;

    case WaterSanitation = 'water_sanitation';
    case Roads = 'roads';
    case Education = 'education';
    case Health = 'health';
    case Energy = 'energy';
    case Markets = 'markets';
    case PublicBuildings = 'public_buildings';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WaterSanitation => 'Water and sanitation',
            self::Roads => 'Roads and bridges',
            self::Education => 'Education',
            self::Health => 'Health',
            self::Energy => 'Energy',
            self::Markets => 'Markets and livelihoods',
            self::PublicBuildings => 'Public buildings',
            self::Other => 'Other',
        };
    }
}
