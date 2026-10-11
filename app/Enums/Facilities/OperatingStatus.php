<?php

namespace App\Enums\Facilities;

use App\Enums\Concerns\HasOptions;

enum OperatingStatus: string
{
    use HasOptions;

    case Operational = 'operational';
    case PartiallyOperational = 'partially_operational';
    case Closed = 'closed_down';
    case UnderConstruction = 'under_construction';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Operational => 'Operational',
            self::PartiallyOperational => 'Partially operational',
            self::Closed => 'Closed',
            self::UnderConstruction => 'Under construction',
            self::Unknown => 'Unknown',
        };
    }
}
