<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Actions\Geography\ApplyGeoReassignment;
use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Enums\Geography\ReassignmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Geography\GeoArea;
use App\Models\Geography\GeoReassignment;
use App\Services\Geography\GeoAreaResolver;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class GeoReassignmentController extends Controller
{
    public function index(Request $request, GeoScope $geoScope): Response
    {
        $user = $request->user();

        abort_unless(
            $user->can(Permission::GeographyReassign->value) || $user->can(Permission::GeographyApproveReassignment->value),
            403,
        );

        $status = $request->string('status')->toString();
        $scopes = $geoScope->for($user);

        $reassignments = GeoReassignment::query()
            ->with(['requester:id,name', 'decider:id,name'])
            ->when(ReassignmentStatus::tryFrom($status) !== null, fn (Builder $query) => $query->where('status', $status))
            ->when(! $scopes->national, function (Builder $query) use ($scopes): void {
                $query->where(function (Builder $inner) use ($scopes): void {
                    foreach ($scopes->areas as $area) {
                        $column = $area['level']->column();
                        $inner->orWhere("from_path->{$column}", $area['id'])->orWhere("to_path->{$column}", $area['id']);
                    }
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (GeoReassignment $reassignment): array => $reassignment->toSummary());

        return Inertia::render('admin/geography/reassignments', [
            'reassignments' => $reassignments,
            'filters' => ['status' => $status],
            'statusOptions' => ReassignmentStatus::options(),
            'can' => ['decide' => $user->can(Permission::GeographyApproveReassignment->value)],
        ]);
    }

    public function store(Request $request, GeoLevel $level, GeoArea $geoArea, GeoAreaResolver $resolver, GeoScope $geoScope): RedirectResponse
    {
        Gate::authorize('requestReassignment', $geoArea);

        $parentLevel = $level->parent();

        $validated = $request->validate([
            'to_parent_id' => ['required', 'integer', Rule::exists($parentLevel->table(), 'id'), Rule::notIn([$geoArea->getAttribute($parentLevel->column())])],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'effective_on' => ['required', 'date'],
        ], [
            'to_parent_id.not_in' => 'Choose a different parent from the current one.',
        ]);

        $newParent = $resolver->find($parentLevel, (int) $validated['to_parent_id']);

        if (! $geoScope->allowsPath($request->user(), $resolver->fullPath($newParent))) {
            throw ValidationException::withMessages(['to_parent_id' => 'You can only move areas into parents within your assignment.']);
        }

        $hasPending = GeoReassignment::query()
            ->where('level', $level->value)
            ->where('area_id', $geoArea->id)
            ->where('status', ReassignmentStatus::Pending)
            ->exists();

        if ($hasPending) {
            throw ValidationException::withMessages(['to_parent_id' => 'A reassignment for this area is already awaiting a decision.']);
        }

        GeoReassignment::query()->create([
            'level' => $level,
            'area_id' => $geoArea->id,
            'area_reference_code' => $geoArea->reference_code,
            'area_name' => $geoArea->name,
            'from_parent_id' => $geoArea->getAttribute($parentLevel->column()),
            'to_parent_id' => $newParent->id,
            'from_path' => $resolver->fullPath($geoArea),
            'to_path' => [...$resolver->fullPath($newParent), $level->column() => $geoArea->id],
            'reason' => $validated['reason'],
            'effective_on' => $validated['effective_on'],
            'requested_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Reassignment requested. It takes effect once approved.')]);

        return back();
    }

    public function update(Request $request, GeoReassignment $reassignment, ApplyGeoReassignment $apply, GeoScope $geoScope): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->can(Permission::GeographyApproveReassignment->value), 403);
        abort_unless(
            $geoScope->allowsPath($user, $reassignment->from_path) && $geoScope->allowsPath($user, $reassignment->to_path),
            404,
        );

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ]);

        if ($validated['decision'] === 'approve') {
            $apply->approve($reassignment, $user, $validated['note'] ?? null);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Reassignment approved and applied.')]);
        } else {
            $apply->reject($reassignment, $user, $validated['note']);
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Reassignment rejected.')]);
        }

        return back();
    }
}
