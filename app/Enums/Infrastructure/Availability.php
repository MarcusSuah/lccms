<?php

namespace App\Enums\Infrastructure;

use App\Enums\Concerns\HasOptions;

enum Availability: string
{
    use HasOptions;

    case Available = 'available';
    case Limited = 'limited';
    case Unavailable = 'unavailable';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Available',
            self::Limited => 'Limited',
            self::Unavailable => 'Unavailable',
        };
    }
}
