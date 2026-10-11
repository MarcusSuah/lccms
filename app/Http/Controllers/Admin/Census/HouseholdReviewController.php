<?php

namespace App\Http\Controllers\Admin\Census;

use App\Actions\Census\CensusWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Census\Household;
use App\Services\Census\CensusAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Verify, approve, return or archive a household. The workflow action
 * re-checks permission, geographic reach and the expected record version.
 */
class HouseholdReviewController extends Controller
{
    public function store(Request $request, Household $household, CensusWorkflow $workflow, CensusAccess $access): RedirectResponse
    {
        abort_unless($access->canAccess($request->user(), $household), 404);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['verify', 'approve', 'reject', 'archive'])],
            'note' => ['nullable', 'string', 'max:2000', Rule::requiredIf(in_array($request->input('action'), ['reject', 'archive'], true))],
            'version' => ['required', 'integer'],
        ], [
            'note.required' => 'Explain why the household is being returned or archived.',
        ]);

        if ((int) $validated['version'] !== $household->version) {
            throw ValidationException::withMessages(['version' => 'This household changed since you opened it. Reload and review it again.']);
        }

        $user = $request->user();
        $note = $validated['note'] ?? null;

        match ($validated['action']) {
            'verify' => $workflow->verify($user, $household, $note),
            'approve' => $workflow->approve($user, $household, $note),
            'reject' => $workflow->reject($user, $household, (string) $note),
            'archive' => $workflow->archive($user, $household, (string) $note),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Household :action.', ['action' => match ($validated['action']) {
            'verify' => 'verified', 'approve' => 'approved', 'reject' => 'returned to the enumerator', 'archive' => 'archived',
        }])]);

        return back();
    }
}
