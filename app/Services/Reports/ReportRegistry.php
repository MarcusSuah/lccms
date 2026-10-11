<?php

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Reports\Definitions\AgeSexReport;
use App\Services\Reports\Definitions\CampaignComparisonReport;
use App\Services\Reports\Definitions\ComplaintsReport;
use App\Services\Reports\Definitions\EnumeratorPerformanceReport;
use App\Services\Reports\Definitions\FacilitiesReport;
use App\Services\Reports\Definitions\LeadershipReport;
use App\Services\Reports\Definitions\OccupantListReport;
use App\Services\Reports\Definitions\PopulationReport;
use App\Services\Reports\Definitions\SupervisorWorkloadReport;
use App\Services\Reports\Definitions\SyncOutcomesReport;

/**
 * The fixed catalogue of reports. Keys come from here, never from user input
 * mapped to class names.
 */
class ReportRegistry
{
    /**
     * @var list<class-string<ReportDefinition>>
     */
    private const REPORTS = [
        PopulationReport::class,
        AgeSexReport::class,
        CampaignComparisonReport::class,
        OccupantListReport::class,
        EnumeratorPerformanceReport::class,
        SupervisorWorkloadReport::class,
        SyncOutcomesReport::class,
        FacilitiesReport::class,
        LeadershipReport::class,
        ComplaintsReport::class,
    ];

    /**
     * @return list<ReportDefinition>
     */
    public function all(): array
    {
        return array_map(fn (string $class): ReportDefinition => app($class), self::REPORTS);
    }

    public function find(string $key): ?ReportDefinition
    {
        foreach ($this->all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }

    /**
     * @return list<ReportDefinition>
     */
    public function availableTo(User $user): array
    {
        return array_values(array_filter($this->all(), fn (ReportDefinition $report): bool => $report->authorize($user)));
    }
}
