<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

/**
 * Role a user holds within one campaign assignment.
 */
enum AssignmentRole: string
{
    use HasOptions;

    case Enumerator = 'enumerator';
    case Supervisor = 'supervisor';

    public function label(): string
    {
        return match ($this) {
            self::Enumerator => 'Enumerator',
            self::Supervisor => 'Supervisor',
        };
    }
}
