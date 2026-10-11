<?php

namespace App\Http\Controllers\Admin\Census;

use App\Actions\Census\SaveCampaign;
use App\Enums\Access\Permission;
use App\Enums\Census\AssignmentRole;
use App\Enums\Census\CampaignStatus;
use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CampaignScope;
use App\Models\Census\CensusCampaign;
use App\Services\Census\CensusAccess;
use App\Services\Census\CensusDataQuality;
use App\Services\Census\CensusStatistics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CampaignController extends Controller
{
    public function index(Request $request, CensusAccess $access): Response
    {
        abort_unless($request->user()->can(Permission::CampaignsView->value), 403);

        $visible = $access->visibleCampaignIds($request->user());

        $campaigns = CensusCampaign::query()
            ->when($visible !== null, fn ($query) => $query->whereIn('id', $visible))
            ->withCount(['households', 'assignments'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (CensusCampaign $campaign): array => $this->summary($campaign));

        return Inertia::render('admin/census/campaigns/index', [
            'campaigns' => $campaigns,
            'can' => ['create' => $request->user()->can(Permission::CampaignsManage->value)],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->can(Permission::CampaignsManage->value), 403);

        return Inertia::render('admin/census/campaigns/form', ['campaign' => null]);
    }

    public function store(Request $request, SaveCampaign $save): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::CampaignsManage->value), 403);

        $campaign = $save->handle($this->validated($request));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign created.')]);

        return to_route('admin.census.campaigns.show', $campaign);
    }

    public function show(Request $request, CensusCampaign $campaign, CensusAccess $access, CensusStatistics $statistics, CensusDataQuality $quality): Response
    {
        $user = $request->user();
        $this->authorizeView($request, $campaign, $access);

        $campaign->load(['scopes' => fn ($query) => $query->with(CampaignScope::areaRelations())]);

        $assignments = $campaign->assignments()
            ->with(['user:id,name,email', 'supervisor:id,name', ...CampaignAssignment::areaRelations()])
            ->when(! $user->can(Permission::GeoNational->value), fn ($query) => $query->visibleTo($user))
            ->orderBy('status')
            ->orderBy('role')
            ->orderBy('id')
            ->get();

        $enumeratorProgress = $campaign->households()
            ->toBase()
            ->select('enumerator_id')
            ->selectRaw("SUM(status <> 'archived') AS registered, SUM(status = 'approved') AS approved, SUM(status = 'rejected') AS returned")
            ->groupBy('enumerator_id')
            ->get()
            ->keyBy('enumerator_id');

        return Inertia::render('admin/census/campaigns/show', [
            'campaign' => [
                ...$this->summary($campaign),
                'description' => $campaign->description,
                'instructions' => $campaign->instructions,
                'scopes' => $campaign->scopes->map(fn (CampaignScope $scope): array => [
                    'id' => $scope->id,
                    'label' => $scope->areaLabel(),
                ]),
                'allowed_transitions' => array_map(
                    fn (CampaignStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    $campaign->status->allowedTransitions(),
                ),
            ],
            'summary' => $statistics->summary($campaign, $user),
            'quality' => $quality->report($campaign, $user),
            'assignments' => $assignments->map(fn (CampaignAssignment $assignment): array => [
                'id' => $assignment->id,
                'user' => $assignment->user?->only(['id', 'name', 'email']),
                'role' => $assignment->role->value,
                'supervisor' => $assignment->supervisor?->name,
                'area' => $assignment->areaLabel(),
                'starts_on' => $assignment->starts_on->toDateString(),
                'ends_on' => $assignment->ends_on->toDateString(),
                'status' => $assignment->status->value,
                'expected_houses' => $assignment->expected_houses,
                'progress' => $assignment->role === AssignmentRole::Enumerator ? [
                    'registered' => (int) ($enumeratorProgress[$assignment->user_id]->registered ?? 0),
                    'approved' => (int) ($enumeratorProgress[$assignment->user_id]->approved ?? 0),
                    'returned' => (int) ($enumeratorProgress[$assignment->user_id]->returned ?? 0),
                ] : null,
            ]),
            'roleOptions' => AssignmentRole::options(),
            'can' => [
                'manage' => $user->can(Permission::CampaignsManage->value),
                'assign' => $user->can(Permission::AssignmentsManage->value) && ! $campaign->status->isFinal(),
            ],
        ]);
    }

    public function edit(Request $request, CensusCampaign $campaign): Response
    {
        abort_unless($request->user()->can(Permission::CampaignsManage->value), 403);

        $campaign->load(['scopes' => fn ($query) => $query->with(CampaignScope::areaRelations())]);

        return Inertia::render('admin/census/campaigns/form', [
            'campaign' => [
                ...$this->summary($campaign),
                'description' => $campaign->description,
                'instructions' => $campaign->instructions,
                'coverage_locked' => ! in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Planned], true),
                'scopes' => $campaign->scopes->map(fn (CampaignScope $scope): array => [
                    'level' => $scope->level()->value,
                    'area_id' => $scope->areaId(),
                    'label' => $scope->areaLabel(),
                ]),
            ],
        ]);
    }

    public function update(Request $request, CensusCampaign $campaign, SaveCampaign $save): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::CampaignsManage->value), 403);

        $save->handle($this->validated($request, $campaign), $campaign);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Campaign updated.')]);

        return to_route('admin.census.campaigns.show', $campaign);
    }

    private function authorizeView(Request $request, CensusCampaign $campaign, CensusAccess $access): void
    {
        abort_unless($request->user()->can(Permission::CampaignsView->value), 403);

        $visible = $access->visibleCampaignIds($request->user());
        abort_if($visible !== null && ! in_array($campaign->id, $visible, true), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?CensusCampaign $campaign = null): array
    {
        return $request->validate([
            'reference_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9][A-Z0-9-]*$/', Rule::unique('census_campaigns', 'reference_code')->ignore($campaign?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'instructions' => ['nullable', 'string', 'max:20000'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reference_date' => ['required', 'date'],
            'scopes' => ['array', 'max:200'],
            'scopes.*.level' => ['required', Rule::enum(GeoLevel::class)],
            'scopes.*.area_id' => ['required', 'integer', 'min:1'],
        ], [
            'reference_code.regex' => 'Use capital letters, digits and hyphens only.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(CensusCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'reference_code' => $campaign->reference_code,
            'name' => $campaign->name,
            'status' => $campaign->status->value,
            'start_date' => $campaign->start_date->toDateString(),
            'end_date' => $campaign->end_date->toDateString(),
            'reference_date' => $campaign->reference_date->toDateString(),
            'closed_at' => $campaign->closed_at?->toIso8601String(),
            'households_count' => $campaign->households_count ?? null,
            'assignments_count' => $campaign->assignments_count ?? null,
        ];
    }
}
