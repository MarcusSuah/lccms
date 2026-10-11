<?php

namespace App\Enums\Facilities;

use App\Enums\Concerns\HasOptions;

enum ServiceAssessmentStatus: string
{
    use HasOptions;

    case Present = 'present';
    case VerifiedAbsent = 'verified_absent';
    case NotAssessed = 'not_assessed';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::VerifiedAbsent => 'Verified absent',
            self::NotAssessed => 'Not assessed',
        };
    }
}
