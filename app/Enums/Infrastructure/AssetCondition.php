<?php

namespace App\Enums\Infrastructure;

use App\Enums\Concerns\HasOptions;

enum AssetCondition: string
{
    use HasOptions;

    case Good = 'good';
    case Fair = 'fair';
    case Poor = 'poor';
    case NonFunctional = 'not_functional';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Good => 'Good',
            self::Fair => 'Fair',
            self::Poor => 'Poor',
            self::NonFunctional => 'Not functional',
            self::Unknown => 'Unknown',
        };
    }

    public function isGap(): bool
    {
        return in_array($this, [self::Poor, self::NonFunctional], true);
    }
}
