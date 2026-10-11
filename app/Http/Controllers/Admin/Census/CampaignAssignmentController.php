<?php

namespace App\Http\Controllers\Admin\Census;

use App\Actions\Census\SaveAssignment;
use App\Enums\Access\Permission;
use App\Enums\Census\AssignmentRole;
use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\User;
use App\Services\Geography\GeoScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Assignment of enumerators and supervisors to campaign areas. Reassignment
 * creates a new assignment linked to the one it replaces.
 */
class CampaignAssignmentController extends Controller
{
    public function store(Request $request, CensusCampaign $campaign, SaveAssignment $save): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::AssignmentsManage->value), 403);

        $replaces = null;

        if ($request->filled('replaces_id')) {
            $replaces = $campaign->assignments()->whereKey($request->integer('replaces_id'))->firstOrFail();
            abort_unless(app(GeoScope::class)->allowsPath($request->user(), $replaces->path()), 404);
        }

        $save->create($request->user(), $campaign, $this->validated($request, $campaign), $replaces);

        Inertia::flash('toast', ['type' => 'success', 'message' => $replaces ? __('Work reassigned.') : __('Assignment created.')]);

        return back();
    }

    public function destroy(Request $request, CensusCampaign $campaign, CampaignAssignment $assignment, SaveAssignment $save): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::AssignmentsManage->value), 403);
        abort_unless($assignment->campaign_id === $campaign->id, 404);
        abort_unless(app(GeoScope::class)->allowsPath($request->user(), $assignment->path()), 404);

        $save->revoke($assignment);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Assignment revoked. Records already collected are kept.')]);

        return back();
    }

    /**
     * Staff who could take an assignment, limited to the administrator's reach.
     */
    public function candidates(Request $request, CensusCampaign $campaign, GeoScope $geoScope): JsonResponse
    {
        abort_unless($request->user()->can(Permission::AssignmentsManage->value), 403);

        $role = AssignmentRole::tryFrom($request->string('role')->toString()) ?? AssignmentRole::Enumerator;
        $permission = $role === AssignmentRole::Enumerator ? Permission::CensusEnumerate : Permission::CensusVerify;
        $search = $request->string('search')->trim()->toString();
        $actor = $request->user();

        $users = User::query()
            ->where('is_active', true)
            ->permission($permission->value)
            ->when(! $geoScope->for($actor)->national, fn ($query) => $query->whereHas('geoScopes', fn ($scopes) => $geoScope->apply($scopes, $actor)))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%")))
            ->orderBy('name')
            ->limit(50)
            ->get(['id', 'name', 'email']);

        return response()->json(['data' => $users->map(fn (User $user): array => [
            'value' => $user->id,
            'label' => "{$user->name} ({$user->email})",
        ])]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, CensusCampaign $campaign): array
    {
        return $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::enum(AssignmentRole::class)],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'level' => ['required', Rule::enum(GeoLevel::class)],
            'area_id' => ['required', 'integer', 'min:1'],
            'starts_on' => ['required', 'date', 'after_or_equal:'.$campaign->start_date->toDateString(), 'before_or_equal:'.$campaign->end_date->toDateString()],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'before_or_equal:'.$campaign->end_date->toDateString()],
            'expected_houses' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
