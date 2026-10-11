<?php

namespace App\Enums\Census;

use App\Enums\Concerns\HasOptions;

enum SchoolAttendance: string
{
    use HasOptions;

    case Never = 'never';
    case Currently = 'currently';
    case Previously = 'previously';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::Never => 'Never attended',
            self::Currently => 'Currently attending',
            self::Previously => 'Attended in the past',
            self::NotApplicable => 'Not applicable (under school age)',
        };
    }
}
