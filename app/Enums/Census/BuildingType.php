<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

/**
 * Configurable proposal; confirm against the approved methodology.
 */
enum BuildingType: string
{
    use HasOptions;

    case Detached = 'detached';
    case SemiDetached = 'semi_detached';
    case Apartment = 'apartment';
    case Compound = 'compound';
    case Traditional = 'traditional';
    case Makeshift = 'makeshift';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Detached => 'Detached house',
            self::SemiDetached => 'Semi-detached / row house',
            self::Apartment => 'Apartment building',
            self::Compound => 'Compound / multi-unit yard',
            self::Traditional => 'Traditional hut',
            self::Makeshift => 'Makeshift / temporary structure',
            self::Other => 'Other',
        };
    }
}
