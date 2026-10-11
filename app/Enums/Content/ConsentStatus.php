<?php

namespace App\Enums\Content;

use App\Enums\Concerns\HasOptions;

/**
 * Consent of identifiable people shown in a photograph.
 */
enum ConsentStatus: string
{
    use HasOptions;

    case NotRequired = 'not_required';
    case Obtained = 'obtained';
    case Pending = 'pending';
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'No identifiable people',
            self::Obtained => 'Consent obtained',
            self::Pending => 'Consent pending',
            self::Refused => 'Consent refused',
        };
    }
}
