<?php

namespace App\Http\Controllers\Admin\Census;

use App\Actions\Census\DecideCorrection;
use App\Actions\Census\RequestCorrection;
use App\Enums\Access\Permission;
use App\Enums\Census\CorrectionStatus;
use App\Http\Controllers\Controller;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\Census\RecordCorrection;
use App\Services\Census\CensusAccess;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CorrectionController extends Controller
{
    public function index(Request $request, GeoScope $geoScope): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::CensusCorrect->value) || $user->can(Permission::CensusApproveCorrection->value), 403);

        $status = CorrectionStatus::tryFrom($request->string('status')->toString()) ?? CorrectionStatus::Pending;

        $corrections = RecordCorrection::query()
            ->where('status', $status)
            ->whereHasMorph('correctable', [Household::class, Occupant::class], fn (Builder $query) => $geoScope->apply($query, $user))
            ->with(['requester:id,name', 'decider:id,name', 'correctable'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (RecordCorrection $correction): array => [
                'id' => $correction->id,
                'record_reference' => $correction->correctable->reference_code,
                'household_id' => $correction->correctable instanceof Household ? $correction->correctable->id : $correction->correctable->household_id,
                'type' => $correction->correctable instanceof Household ? 'Household' : 'Occupant',
                'fields' => array_keys($correction->changes),
                'reason' => $correction->reason,
                'status' => $correction->status->value,
                'requested_by' => $correction->requester?->name,
                'requested_by_me' => $correction->requested_by === $user->id,
                'decided_by' => $correction->decider?->name,
                'created_at' => $correction->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/census/corrections', [
            'corrections' => $corrections,
            'filters' => ['status' => $status->value],
            'statusOptions' => CorrectionStatus::options(),
            'can' => ['decide' => $user->can(Permission::CensusApproveCorrection->value)],
        ]);
    }

    public function store(Request $request, RequestCorrection $requestCorrection, CensusAccess $access): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(['household', 'occupant'])],
            'id' => ['required', 'integer'],
            'changes' => ['required', 'array', 'min:1'],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $record = $validated['type'] === 'household'
            ? Household::query()->findOrFail($validated['id'])
            : Occupant::query()->findOrFail($validated['id']);

        abort_unless($access->canAccess($request->user(), $record), 404);

        $requestCorrection->handle($request->user(), $record, $validated['changes'], $validated['reason']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Correction submitted for approval.')]);

        return back();
    }

    public function update(Request $request, RecordCorrection $correction, DecideCorrection $decide): RedirectResponse
    {
        $validated = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'note' => ['nullable', 'string', 'max:2000', 'required_if:decision,reject'],
        ]);

        if ($validated['decision'] === 'approve') {
            $decide->approve($request->user(), $correction, $validated['note'] ?? null);
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Correction applied.')]);
        } else {
            $decide->reject($request->user(), $correction, $validated['note']);
            Inertia::flash('toast', ['type' => 'info', 'message' => __('Correction rejected.')]);
        }

        return back();
    }
}
