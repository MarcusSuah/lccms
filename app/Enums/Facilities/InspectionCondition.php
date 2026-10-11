<?php

namespace App\Enums\Facilities;

use App\Enums\Concerns\HasOptions;

enum InspectionCondition: string
{
    use HasOptions;

    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Poor => 'Poor',
        };
    }
}
