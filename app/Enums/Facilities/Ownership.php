<?php

namespace App\Enums\Facilities;

use App\Enums\Concerns\HasOptions;

enum Ownership: string
{
    use HasOptions;

    case Public = 'public';
    case Private = 'private';
    case FaithBased = 'faith_based';
    case Ngo = 'ngo';
    case Community = 'community';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public / government',
            self::Private => 'Private',
            self::FaithBased => 'Faith-based',
            self::Ngo => 'NGO',
            self::Community => 'Community-owned',
            self::Other => 'Other',
        };
    }
}
