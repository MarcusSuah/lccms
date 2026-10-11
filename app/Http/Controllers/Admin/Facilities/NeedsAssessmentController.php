<?php

namespace App\Http\Controllers\Admin\Facilities;

use App\Enums\Access\Permission;
use App\Enums\Facilities\ServiceAssessmentStatus;
use App\Enums\Facilities\ServiceCategory;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Facilities\CommunityServiceAssessment;
use App\Models\Geography\Community;
use App\Services\Facilities\NeedsAssessment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Service availability by community and the recording of verified presence
 * or absence of services.
 */
class NeedsAssessmentController extends Controller
{
    use AuthorizesAreas;

    public function index(Request $request, NeedsAssessment $assessment): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, [Permission::FacilitiesView, Permission::InfrastructureView]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'gaps_only' => ['nullable', 'boolean'],
        ]);

        return Inertia::render('admin/facilities/needs-assessment', [
            'rows' => $assessment->report($user, [
                'search' => $filters['search'] ?? null,
                'gaps_only' => (bool) ($filters['gaps_only'] ?? false),
            ]),
            'services' => ServiceCategory::options(),
            'filters' => ['search' => $filters['search'] ?? '', 'gaps_only' => (bool) ($filters['gaps_only'] ?? false)],
            'assessmentOptions' => ServiceAssessmentStatus::options(),
            'can' => ['assess' => $user->can(Permission::FacilitiesManage->value) || $user->can(Permission::InfrastructureManage->value)],
        ]);
    }

    public function update(Request $request, Community $community): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, [Permission::FacilitiesManage, Permission::InfrastructureManage], $community->pathAttributes());

        $validated = $request->validate([
            'category' => ['required', Rule::enum(ServiceCategory::class)],
            'status' => ['required', Rule::enum(ServiceAssessmentStatus::class)],
            'assessed_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $assessment = CommunityServiceAssessment::query()->firstOrNew([
            'community_id' => $community->id,
            'category' => $validated['category'],
        ]);
        $assessment->fill($validated);
        $assessment->forceFill([...$community->pathAttributes(), 'category' => $validated['category'], 'assessed_by' => $user->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Assessment recorded.')]);

        return back();
    }
}
