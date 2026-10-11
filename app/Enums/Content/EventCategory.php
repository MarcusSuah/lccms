<?php

namespace App\Enums\Content;

use App\Enums\Concerns\HasOptions;

enum EventCategory: string
{
    use HasOptions;

    case CommunityMeeting = 'community_meeting';
    case Health = 'health';
    case Education = 'education';
    case Census = 'census';
    case Development = 'development';
    case Culture = 'culture';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CommunityMeeting => 'Community meeting',
            self::Health => 'Health',
            self::Education => 'Education',
            self::Census => 'Census',
            self::Development => 'Development',
            self::Culture => 'Culture and sports',
            self::Other => 'Other',
        };
    }
}
