<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Census\Sex;
use App\Models\Census\CensusCampaign;
use App\Models\User;
use App\Services\Census\CensusStatistics;
use App\Services\Reports\ReportDefinition;

/**
 * Approved totals for the same area across every campaign the viewer can
 * see, oldest first, so change over time reads top to bottom.
 */
class CampaignComparisonReport extends ReportDefinition
{
    public function __construct(private CensusStatistics $statistics) {}

    public function key(): string
    {
        return 'campaign-comparison';
    }

    public function title(): string
    {
        return 'Campaign comparison';
    }

    public function description(): string
    {
        return 'Approved houses, households and occupants for one area across census campaigns.';
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
        return ['area' => []];
    }

    public function columns(array $filters): array
    {
        return [
            self::text('campaign', 'Campaign'),
            self::text('reference_date', 'Reference date'),
            self::text('status', 'Campaign status'),
            self::number('houses', 'Houses (approved)'),
            self::number('households', 'Households (approved)'),
            self::number('occupants', 'Occupants (approved)'),
            self::number('male', Sex::Male->label()),
            self::number('female', Sex::Female->label()),
            self::number('adults', 'Adults (18+)'),
            self::number('children', 'Children (under 18)'),
            self::number('average_household_size', 'Average household size'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        return $this->campaignsFor($user)
            ->sortBy('reference_date')
            ->map(function (CensusCampaign $campaign) use ($user, $filters): array {
                $summary = $this->statistics->summary($campaign, $user, $filters['area_path']);

                return [
                    'campaign' => "{$campaign->name} ({$campaign->reference_code})",
                    'reference_date' => $campaign->reference_date->toDateString(),
                    'status' => $campaign->status->label(),
                    'houses' => $summary['houses']['approved'],
                    'households' => $summary['households']['approved'],
                    'occupants' => $summary['occupants']['approved'],
                    'male' => $summary['sex'][Sex::Male->value] ?? 0,
                    'female' => $summary['sex'][Sex::Female->value] ?? 0,
                    'adults' => $summary['adults'],
                    'children' => $summary['children'],
                    'average_household_size' => $summary['average_household_size'],
                ];
            })
            ->values()
            ->all();
    }
}
