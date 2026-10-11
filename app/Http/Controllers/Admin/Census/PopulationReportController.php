<?php

namespace App\Http\Controllers\Admin\Census;

use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use App\Models\Census\CensusCampaign;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusStatistics;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Population by geographic level with drill-down, computed from individual
 * records and restricted to the viewer's reach.
 */
class PopulationReportController extends Controller
{
    public function __invoke(Request $request, CensusAccess $access, CensusStatistics $statistics, GeoAreaResolver $resolver): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::CensusView->value) || $user->can(Permission::ReportsView->value), 403);

        $validated = $request->validate([
            'campaign' => ['nullable', 'integer'],
            'level' => ['nullable', Rule::enum(GeoLevel::class)],
            'parent' => ['nullable', 'integer'],
        ]);

        $visible = $access->visibleCampaignIds($user);
        $campaigns = CensusCampaign::query()
            ->when($visible !== null, fn ($query) => $query->whereIn('id', $visible))
            ->orderByDesc('start_date')
            ->get(['id', 'name', 'reference_code', 'status', 'reference_date']);

        $campaign = $campaigns->firstWhere('id', (int) ($validated['campaign'] ?? 0)) ?? $campaigns->first();
        $level = GeoLevel::tryFrom($validated['level'] ?? '') ?? GeoLevel::County;

        $parentArea = null;
        $parentFilter = [];

        if ($level->parent() !== null && isset($validated['parent'])) {
            $parentArea = $resolver->find($level->parent(), (int) $validated['parent']);
            abort_if($parentArea === null, 404);
            $parentArea->load($parentArea::ancestorRelations());
            $parentFilter = [$level->parent()->column() => $parentArea->id];
        }

        return Inertia::render('admin/census/population', [
            'campaigns' => $campaigns->map(fn (CensusCampaign $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'reference_code' => $item->reference_code,
                'status' => $item->status->value,
            ]),
            'campaign' => $campaign === null ? null : [
                ...$campaign->only(['id', 'name', 'reference_code']),
                'reference_date' => $campaign->reference_date->toDateString(),
            ],
            'level' => $level->value,
            'childLevel' => $level->child()?->value,
            'parent' => $parentArea === null ? null : [
                'id' => $parentArea->id,
                'name' => $parentArea->name,
                'trail' => array_map(fn ($area): array => ['id' => $area->id, 'name' => $area->name, 'level' => $area::level()->value], $parentArea->breadcrumb()),
            ],
            'summary' => $campaign === null ? null : $statistics->summary($campaign, $user, $parentFilter),
            'rows' => $campaign === null ? [] : $statistics->breakdown($campaign, $user, $level, $parentFilter),
        ]);
    }
}
