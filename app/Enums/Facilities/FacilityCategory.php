<?php

namespace App\Enums\Facilities;

use App\Enums\Concerns\HasOptions;

/**
 * Reporting category of a facility. Each facility belongs to exactly one.
 */
enum FacilityCategory: string
{
    use HasOptions;

    case School = 'school';
    case Hospital = 'hospital';
    case Clinic = 'clinic';
    case OtherHealth = 'other_health';
    case Market = 'market';
    case Sanitation = 'sanitation';
    case OtherService = 'other_service';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::Hospital => 'Hospital',
            self::Clinic => 'Clinic',
            self::OtherHealth => 'Other health facility',
            self::Market => 'Market',
            self::Sanitation => 'Public toilet / sanitation',
            self::OtherService => 'Other community service',
        };
    }

    public function isHealth(): bool
    {
        return in_array($this, [self::Hospital, self::Clinic, self::OtherHealth], true);
    }
}
