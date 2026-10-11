<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

/**
 * Census sex categories; confirm against the approved methodology.
 */
enum Sex: string
{
    use HasOptions;

    case Male = 'male';
    case Female = 'female';
    case NotStated = 'not_stated';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
            self::NotStated => 'Not stated',
        };
    }
}
