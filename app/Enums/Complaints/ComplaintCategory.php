<?php

namespace App\Enums\Complaints;

use App\Enums\Concerns\HasOptions;

enum ComplaintCategory: string
{
    use HasOptions;

    case PublicService = 'public_service';
    case Water = 'water';
    case Sanitation = 'sanitation';
    case Roads = 'roads';
    case Electricity = 'electricity';
    case Health = 'health';
    case Education = 'education';
    case Market = 'market';
    case Security = 'security';
    case Census = 'census';
    case Leadership = 'leadership';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::PublicService => 'Public service',
            self::Water => 'Water',
            self::Sanitation => 'Sanitation and waste',
            self::Roads => 'Roads and bridges',
            self::Electricity => 'Electricity',
            self::Health => 'Health facility',
            self::Education => 'School or education',
            self::Market => 'Market',
            self::Security => 'Community safety',
            self::Census => 'Census or enumeration',
            self::Leadership => 'Community leadership',
            self::Other => 'Other',
        };
    }
}
