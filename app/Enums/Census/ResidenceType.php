<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

/**
 * Whether a household usually lives in the house.
 */
enum ResidenceType: string
{
    use HasOptions;

    case Usual = 'usual';
    case Temporary = 'temporary';

    public function label(): string
    {
        return match ($this) {
            self::Usual => 'Usual residence',
            self::Temporary => 'Temporary residence',
        };
    }
}
