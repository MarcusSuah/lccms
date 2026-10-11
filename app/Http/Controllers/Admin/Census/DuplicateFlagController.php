<?php

namespace App\Http\Controllers\Admin\Census;

use App\Enums\Access\Permission;
use App\Enums\Census\DuplicateStatus;
use App\Http\Controllers\Controller;
use App\Models\Census\DuplicateFlag;
use App\Models\Census\Occupant;
use App\Services\Census\CensusAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Human review of possible double registrations. Reviewers decide whether
 * two records describe the same person; nothing is merged automatically.
 * A confirmed duplicate that is still in review is fixed by returning its
 * household; an approved one needs a correction or archival decision.
 */
class DuplicateFlagController extends Controller
{
    public function index(Request $request, CensusAccess $access): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::CensusReviewDuplicates->value), 403);

        $status = DuplicateStatus::tryFrom($request->string('status')->toString()) ?? DuplicateStatus::Open;

        $flags = DuplicateFlag::query()
            ->where('status', $status)
            ->whereHas('occupant', fn (Builder $query) => $access->applyToRecords($query, $user))
            ->with(['occupant', 'matchedOccupant', 'reviewer:id,name'])
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (DuplicateFlag $flag): array => [
                'id' => $flag->id,
                'score' => $flag->score,
                'reasons' => $flag->reasons,
                'status' => $flag->status->value,
                'occupant' => $this->describe($flag->occupant, $user, $access),
                'matched' => $this->describe($flag->matchedOccupant, $user, $access),
                'reviewed_by' => $flag->reviewer?->name,
                'review_note' => $flag->review_note,
            ]);

        return Inertia::render('admin/census/duplicates', [
            'flags' => $flags,
            'filters' => ['status' => $status->value],
            'statusOptions' => DuplicateStatus::options(),
        ]);
    }

    public function update(Request $request, DuplicateFlag $flag, CensusAccess $access): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user->can(Permission::CensusReviewDuplicates->value), 403);
        abort_unless($access->canAccess($user, $flag->occupant), 404);

        $validated = $request->validate([
            'status' => ['required', Rule::in([DuplicateStatus::ConfirmedDistinct->value, DuplicateStatus::ConfirmedDuplicate->value])],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $flag->forceFill([
            'status' => $validated['status'],
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
            'review_note' => $validated['note'],
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Review recorded.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function describe(?Occupant $occupant, $user, CensusAccess $access): array
    {
        if ($occupant === null) {
            return [];
        }

        $canSee = $access->canViewPersonalData($user, $occupant);

        return [
            'id' => $occupant->id,
            'reference_code' => $occupant->reference_code,
            'household_id' => $occupant->household_id,
            'full_name' => $canSee ? $occupant->full_name : null,
            'date_of_birth' => $canSee ? $occupant->date_of_birth?->toDateString() : null,
            'age_years' => $occupant->age_years,
            'sex' => $occupant->sex->label(),
            'status' => $occupant->status->value,
        ];
    }
}
