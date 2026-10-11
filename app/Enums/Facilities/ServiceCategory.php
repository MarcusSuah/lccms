<?php

namespace App\Enums\Facilities;

use App\Enums\Concerns\HasOptions;

/**
 * Services whose presence or verified absence is assessed per community.
 */
enum ServiceCategory: string
{
    use HasOptions;

    case School = 'school';
    case HealthFacility = 'health_facility';
    case Market = 'market';
    case PublicToilet = 'public_toilet';
    case Water = 'water';
    case Electricity = 'electricity';
    case Internet = 'internet';
    case WasteCollection = 'waste_collection';
    case RoadAccess = 'road_access';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::HealthFacility => 'Health facility',
            self::Market => 'Market',
            self::PublicToilet => 'Public toilet',
            self::Water => 'Safe water point',
            self::Electricity => 'Electricity',
            self::Internet => 'Internet access',
            self::WasteCollection => 'Waste collection',
            self::RoadAccess => 'All-season road access',
        };
    }
}
