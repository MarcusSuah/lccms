<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

/**
 * Configurable proposal; confirm against the approved methodology.
 */
enum Tenure: string
{
    use HasOptions;

    case Owned = 'owned';
    case Rented = 'rented';
    case RentFree = 'rent_free';
    case Other = 'other';
    case NotStated = 'not_stated';

    public function label(): string
    {
        return match ($this) {
            self::Owned => 'Owned',
            self::Rented => 'Rented',
            self::RentFree => 'Occupied rent-free',
            self::Other => 'Other',
            self::NotStated => 'Not stated',
        };
    }
}
