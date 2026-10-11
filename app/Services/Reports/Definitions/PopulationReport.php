<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Models\Geography\GeoArea;
use App\Models\User;
use App\Services\Census\CensusStatistics;
use App\Services\Reports\ReportDefinition;

/**
 * Houses, households and people per area at a chosen level, computed from
 * individual records. Areas never visited are reported as not enumerated.
 */
class PopulationReport extends ReportDefinition
{
    public function __construct(private CensusStatistics $statistics) {}

    public function key(): string
    {
        return 'population';
    }

    public function title(): string
    {
        return 'Population by area';
    }

    public function description(): string
    {
        return 'Houses visited, households and occupants per area, with approved (official) and registered counts side by side.';
    }

    public function category(): string
    {
        return 'Census';
    }

    public function authorize(User $user): bool
    {
        return $user->can(Permission::CensusView->value) || $user->can(Permission::ReportsView->value);
    }

    public function filters(): array
    {
        return ['campaign' => [], 'area' => [], 'breakdown' => []];
    }

    public function resolveFilters(User $user, array $input): array
    {
        $resolved = parent::resolveFilters($user, $input);

        // Grouping must sit below the chosen area, otherwise the area filter cannot apply.
        if ($resolved['area'] instanceof GeoArea && ! $resolved['area']::level()->isAbove($resolved['breakdown'])) {
            $resolved['breakdown'] = $resolved['area']::level()->child() ?? GeoLevel::Block;
        }

        return $resolved;
    }

    public function columns(array $filters): array
    {
        return [
            self::text('area', $filters['breakdown']->label()),
            self::text('reference_code', 'Reference'),
            self::text('enumerated', 'Enumerated'),
            self::number('houses_visited', 'Houses visited'),
            self::number('houses_approved', 'Houses approved'),
            self::number('households_approved', 'Households (approved)'),
            self::number('occupants_registered', 'Occupants (registered)'),
            self::number('occupants_approved', 'Occupants (approved)'),
            self::number('average_household_size', 'Average household size'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        if ($filters['campaign'] === null) {
            return [];
        }

        $rows = $this->statistics->breakdown($filters['campaign'], $user, $filters['breakdown'], $filters['area_path']);

        return array_map(fn (array $row): array => [
            'area' => $row['name'],
            'reference_code' => $row['reference_code'],
            'enumerated' => $row['is_enumerated'] ? 'Yes' : 'Not enumerated',
            'houses_visited' => $row['houses_visited'],
            'houses_approved' => $row['houses_approved'],
            'households_approved' => $row['households']['approved'],
            'occupants_registered' => $row['occupants']['registered'],
            'occupants_approved' => $row['occupants']['approved'],
            'average_household_size' => $row['average_household_size'],
        ], $rows);
    }
}
