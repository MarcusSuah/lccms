<?php

namespace App\Http\Controllers\Admin\Sync;

use App\Actions\Sync\ResolveSyncConflict;
use App\Enums\Access\Permission;
use App\Enums\Sync\ConflictStatus;
use App\Http\Controllers\Controller;
use App\Models\Census\Occupant;
use App\Models\Sync\SyncConflict;
use App\Services\Census\CensusAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Supervisor queue of offline changes that could not be applied directly.
 * Personal fields in the two versions are shown only to reviewers who may see
 * personal data.
 */
class SyncConflictController extends Controller
{
    public function index(Request $request, CensusAccess $access): Response
    {
        $user = $request->user();
        abort_unless($user->can(Permission::SyncResolveConflicts->value), 403);

        $status = ConflictStatus::tryFrom($request->string('status')->toString()) ?? ConflictStatus::Open;
        $canSeeNames = $user->can(Permission::CensusViewPersonalData->value);
        $hidden = $canSeeNames ? [] : ['full_name', 'date_of_birth', 'contact_name', 'contact_phone', 'latitude', 'longitude'];

        $conflicts = SyncConflict::query()
            ->where('status', $status)
            ->where(fn ($query) => $access->applyToRecords($query, $user))
            ->with(['raisedBy:id,name', 'resolver:id,name'])
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SyncConflict $conflict): array => [
                'id' => $conflict->id,
                'entity_type' => $conflict->entity_type,
                'entity_id' => $conflict->entity_id,
                'conflict_class' => $conflict->conflict_class->value,
                'conflict_label' => $conflict->conflict_class->label(),
                'base_version' => $conflict->base_version,
                'server_version' => $conflict->server_version,
                'server' => array_diff_key($conflict->server_snapshot, array_flip($hidden)),
                'submitted' => array_diff_key($conflict->submitted_payload, array_flip($hidden)),
                'raised_by' => $conflict->raisedBy?->name,
                'resolved_by' => $conflict->resolver?->name,
                'resolution_note' => $conflict->resolution_note,
                'status' => $conflict->status->value,
                'created_at' => $conflict->created_at?->toIso8601String(),
                'household_id' => match ($conflict->entity_type) {
                    'household' => $conflict->entity_id,
                    'occupant' => Occupant::query()->whereKey($conflict->entity_id)->value('household_id'),
                    default => null,
                },
            ]);

        return Inertia::render('admin/sync/conflicts', [
            'conflicts' => $conflicts,
            'filters' => ['status' => $status->value],
            'statusOptions' => ConflictStatus::options(),
            'personalDataHidden' => ! $canSeeNames,
        ]);
    }

    public function update(Request $request, SyncConflict $conflict, ResolveSyncConflict $resolve, CensusAccess $access): RedirectResponse
    {
        abort_unless($access->canAccess($request->user(), $conflict), 404);

        $validated = $request->validate([
            'decision' => ['required', Rule::in(['keep_server', 'apply_client', 'convert_correction'])],
            'note' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $resolve->handle($request->user(), $conflict, $validated['decision'], $validated['note']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Conflict resolved.')]);

        return back();
    }
}
