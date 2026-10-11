<?php

namespace App\Http\Controllers;

use App\Enums\Access\Permission;
use App\Enums\Census\CampaignStatus;
use App\Enums\Census\CensusRecordStatus;
use App\Enums\Complaints\ComplaintPriority;
use App\Enums\Complaints\ComplaintStatus;
use App\Enums\Sync\ConflictStatus;
use App\Models\Census\CensusCampaign;
use App\Models\Census\Household;
use App\Models\Complaints\Complaint;
use App\Models\Sync\SyncConflict;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusStatistics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Role-aware landing page. Every figure is an aggregate of approved records
 * inside the viewer's geographic reach.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request, CensusAccess $access, CensusStatistics $statistics): Response
    {
        $user = $request->user();
        $request->validate(['campaign' => ['nullable', 'integer', Rule::exists('census_campaigns', 'id')]]);

        $canSeeCensus = $user->can(Permission::CensusView->value) || $user->can(Permission::ReportsView->value);
        $campaigns = collect();
        $campaign = null;

        if ($canSeeCensus) {
            $visible = $access->visibleCampaignIds($user);
            $campaigns = CensusCampaign::query()
                ->when($visible !== null, fn ($query) => $query->whereIn('id', $visible))
                ->whereIn('status', [CampaignStatus::Active, CampaignStatus::InReview, CampaignStatus::Closed, CampaignStatus::Archived])
                ->orderByDesc('reference_date')
                ->get(['id', 'name', 'reference_code', 'status', 'reference_date']);

            $campaign = $campaigns->firstWhere('id', $request->integer('campaign')) ?? $campaigns->first();
        }

        $summary = $campaign ? $statistics->summary($campaign, $user) : null;

        return Inertia::render('dashboard', [
            'campaigns' => $campaigns->map(fn (CensusCampaign $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'reference_code' => $item->reference_code,
                'status' => $item->status->value,
            ])->values(),
            'campaign' => $campaign === null ? null : [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'status' => $campaign->status->value,
                'reference_date' => $campaign->reference_date->toDateString(),
            ],
            'summary' => $summary,
            'workload' => $campaign === null ? null : [
                'awaiting_review' => Household::query()
                    ->where('campaign_id', $campaign->id)
                    ->whereIn('status', [CensusRecordStatus::Submitted, CensusRecordStatus::Verified])
                    ->accessibleBy($user)
                    ->count(),
                'open_conflicts' => $user->can(Permission::SyncResolveConflicts->value)
                    ? SyncConflict::query()->where('campaign_id', $campaign->id)->where('status', ConflictStatus::Open)
                        ->where(fn ($query) => $access->applyToRecords($query, $user))->count()
                    : null,
            ],
            'complaints' => $user->can(Permission::ComplaintsView->value) ? $this->complaintCounts($user) : null,
        ]);
    }

    /**
     * Complaints within the viewer's areas by status, plus open urgent ones.
     *
     * @return array{by_status: list<array{status: string, label: string, total: int}>, open: int, urgent_open: int}
     */
    private function complaintCounts(User $user): array
    {
        $closed = [ComplaintStatus::Resolved, ComplaintStatus::Rejected, ComplaintStatus::Closed];

        $totals = Complaint::query()
            ->visibleTo($user)
            ->toBase()
            ->select('status', DB::raw('COUNT(*) AS total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'by_status' => array_map(fn (ComplaintStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'total' => (int) ($totals[$status->value] ?? 0),
            ], ComplaintStatus::cases()),
            'open' => (int) $totals->except(array_map(fn (ComplaintStatus $status): string => $status->value, $closed))->sum(),
            'urgent_open' => Complaint::query()
                ->visibleTo($user)
                ->whereNotIn('status', $closed)
                ->where('priority', ComplaintPriority::Urgent)
                ->count(),
        ];
    }
}
