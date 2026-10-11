<?php

namespace App\Enums\Infrastructure;

use App\Enums\Concerns\HasOptions;

enum InfrastructureCategory: string
{
    use HasOptions;

    case Road = 'road';
    case Bridge = 'bridge';
    case Water = 'water';
    case Electricity = 'electricity';
    case SanitationDrainage = 'sanitation_drainage';
    case WasteManagement = 'waste_management';
    case PublicBuilding = 'public_building';
    case Internet = 'internet';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Road => 'Road',
            self::Bridge => 'Bridge',
            self::Water => 'Water access',
            self::Electricity => 'Electricity',
            self::SanitationDrainage => 'Sanitation and drainage',
            self::WasteManagement => 'Waste management',
            self::PublicBuilding => 'Public building',
            self::Internet => 'Internet access',
            self::Other => 'Other',
        };
    }
}
