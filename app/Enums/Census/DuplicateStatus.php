<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

enum DuplicateStatus: string
{
    use HasOptions;

    case Open = 'open';
    case ConfirmedDistinct = 'confirmed_distinct';
    case ConfirmedDuplicate = 'confirmed_duplicate';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Open',
            self::ConfirmedDistinct => 'Different people',
            self::ConfirmedDuplicate => 'Same person',
        };
    }
}
