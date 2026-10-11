<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use App\Models\Census\CensusCampaign;
use App\Models\Reports\ExportJob;
use App\Models\User;
use App\Services\Reports\ReportDefinition;
use App\Services\Reports\ReportExporter;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Report hub and the generic report page. The page shows a preview of the
 * same rows an export would contain.
 */
class ReportController extends Controller
{
    /**
     * Rows shown on screen; exports contain everything.
     */
    public const PREVIEW_ROWS = 500;

    public function index(Request $request, ReportRegistry $registry): Response
    {
        $user = $request->user();
        $reports = $registry->availableTo($user);
        abort_if($reports === [], 403);

        return Inertia::render('admin/reports/index', [
            'reports' => array_map(fn (ReportDefinition $report): array => [
                ...$this->describe($report),
                'can_export' => $report->canExport($user),
            ], $reports),
            'exports' => ExportJob::query()
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn (ExportJob $export): array => [
                    ...$export->toListArray(),
                    'report_title' => $registry->find($export->report)?->title() ?? $export->report,
                ]),
        ]);
    }

    public function show(Request $request, string $report, ReportRegistry $registry): Response
    {
        $user = $request->user();
        $definition = $registry->find($report);
        abort_if($definition === null || ! $definition->authorize($user), 404);

        $filters = $definition->resolveFilters($user, $request->query());

        $rows = [];
        $truncated = false;

        foreach ($definition->rows($user, $filters) as $row) {
            if (count($rows) === self::PREVIEW_ROWS) {
                $truncated = true;

                break;
            }

            $rows[] = $row;
        }

        if ($definition->containsPersonalData()) {
            activity('reports')
                ->causedBy($user)
                ->event('viewed')
                ->withProperties(['report' => $definition->key(), 'filters' => $definition->serializeFilters($filters), 'rows' => count($rows)])
                ->log("Viewed {$definition->title()}");
        }

        $exports = ExportJob::query()
            ->where('user_id', $user->id)
            ->where('report', $definition->key())
            ->latest('id')
            ->limit(5)
            ->get();

        return Inertia::render('admin/reports/show', [
            'report' => [
                ...$this->describe($definition),
                'can_export' => $definition->canExport($user),
            ],
            'filters' => $definition->serializeFilters($filters),
            'filterSummary' => $definition->describeFilters($filters),
            'area' => isset($filters['area']) ? [
                'level' => $filters['area']::level()->value,
                'id' => $filters['area']->id,
                'path' => $filters['area']->pathAttributes(),
            ] : null,
            'campaigns' => isset($definition->filters()['campaign'])
                ? $this->campaignOptions($definition, $user)
                : [],
            'levels' => GeoLevel::options(),
            'columns' => $definition->columns($filters),
            'rows' => $rows,
            'truncated' => $truncated,
            'previewLimit' => self::PREVIEW_ROWS,
            'formats' => ReportExporter::FORMATS,
            'exports' => $exports->map(fn (ExportJob $export): array => $export->toListArray()),
            'hasPendingExports' => $exports->contains(fn (ExportJob $export): bool => $export->isPending()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(ReportDefinition $report): array
    {
        return [
            'key' => $report->key(),
            'title' => $report->title(),
            'description' => $report->description(),
            'category' => $report->category(),
            'personal_data' => $report->containsPersonalData(),
            'filters' => $report->filters(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, reference_code: string, status: string}>
     */
    private function campaignOptions(ReportDefinition $report, User $user): array
    {
        return $report->campaignsFor($user)
            ->map(fn (CensusCampaign $campaign): array => [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'reference_code' => $campaign->reference_code,
                'status' => $campaign->status->value,
            ])
            ->values()
            ->all();
    }
}
