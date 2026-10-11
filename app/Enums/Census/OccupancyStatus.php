<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

/**
 * Result of the visit to a house in one campaign.
 */
enum OccupancyStatus: string
{
    use HasOptions;

    case Occupied = 'occupied';
    case Vacant = 'vacant';
    case UnderConstruction = 'under_construction';
    case NonResidential = 'non_residential';
    case Refused = 'refused';
    case NotContacted = 'not_contacted';

    public function label(): string
    {
        return match ($this) {
            self::Occupied => 'Occupied',
            self::Vacant => 'Vacant',
            self::UnderConstruction => 'Under construction',
            self::NonResidential => 'Non-residential',
            self::Refused => 'Refused',
            self::NotContacted => 'Not contacted (revisit)',
        };
    }
}
