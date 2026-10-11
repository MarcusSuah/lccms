<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

enum AssignmentStatus: string
{
    use HasOptions;

    case Active = 'active';
    case Completed = 'completed';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Completed => 'Completed',
            self::Revoked => 'Revoked',
        };
    }
}
