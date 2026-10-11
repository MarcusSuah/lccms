<?php

namespace App\Enums;

use App\Enums\Concerns\HasOptions;

enum VerificationStatus: string
{
    use HasOptions;

    case Unverified = 'unverified';
    case Verified = 'verified';
    case Rejected = 'rejected';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
