<?php

namespace App\Http\Controllers\Public;

use App\Actions\Complaints\SubmitComplaint;
use App\Enums\Complaints\ComplaintCategory;
use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use App\Models\Complaints\Complaint;
use App\Models\Complaints\ComplaintStatusChange;
use App\Models\Geography\County;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Honeypot\Honeypot;

/**
 * Public complaint submission and private tracking. No account is needed.
 * A complaint is reachable only with its high-entropy tracking token.
 */
class ComplaintController extends Controller
{
    public function create(Honeypot $honeypot): Response
    {
        return Inertia::render('public/complaints/create', [
            'categories' => ComplaintCategory::options(),
            'counties' => County::query()->active()->orderBy('name')->get(['id', 'name'])->map(fn (County $county): array => ['value' => $county->id, 'label' => $county->name]),
            'maxImages' => SubmitComplaint::MAX_IMAGES,
            'maxImageKilobytes' => (int) config('lccms.uploads.image_max_kilobytes'),
            'honeypot' => $honeypot->toArray(),
        ]);
    }

    public function store(Request $request, SubmitComplaint $submit): RedirectResponse
    {
        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:160'],
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'description' => ['required', 'string', 'min:20', 'max:5000'],
            'level' => ['required', Rule::enum(GeoLevel::class)],
            'area_id' => ['required', 'integer', 'min:1'],
            'house_reference' => ['nullable', 'string', 'max:80'],
            'is_anonymous' => ['boolean'],
            'complainant_name' => ['nullable', 'string', 'max:120'],
            'complainant_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\-\s]{5,40}$/'],
            'complainant_email' => ['nullable', 'email', 'max:255'],
            'images' => ['nullable', 'array', 'max:'.SubmitComplaint::MAX_IMAGES],
            'images.*' => ['file', 'max:'.config('lccms.uploads.image_max_kilobytes'), 'mimes:jpg,jpeg,png,webp'],
        ], [
            'images.max' => 'You can attach at most '.SubmitComplaint::MAX_IMAGES.' images.',
            'images.*.mimes' => 'Only JPEG, PNG or WebP photos can be attached.',
        ]);

        $result = $submit->handle($validated, $request->file('images', []));

        return to_route('complaints.submitted')->with('complaint_submission', [
            'reference' => $result['complaint']->public_reference,
            'tracking_url' => route('complaints.track', $result['token']),
            'has_contact' => $result['complaint']->complainant_email !== null || $result['complaint']->complainant_phone !== null,
        ]);
    }

    public function submitted(Request $request): Response|RedirectResponse
    {
        $submission = $request->session()->get('complaint_submission');

        if ($submission === null) {
            return to_route('complaints.create');
        }

        return Inertia::render('public/complaints/submitted', ['submission' => $submission]);
    }

    public function track(string $token, GeoAreaResolver $resolver): Response
    {
        abort_unless(strlen($token) === 48, 404);

        $complaint = Complaint::query()
            ->where('tracking_token_hash', Complaint::hashToken($token))
            ->with(['history', 'county:id,name', 'community:id,name'])
            ->withCount('attachments')
            ->first();

        abort_if($complaint === null, 404);

        return Inertia::render('public/complaints/track', [
            'complaint' => [
                'reference' => $complaint->public_reference,
                'subject' => $complaint->subject,
                'category' => $complaint->category->label(),
                'status' => $complaint->status->value,
                'status_label' => $complaint->status->label(),
                'area' => collect([$complaint->community?->name, $complaint->county?->name])->filter()->join(', '),
                'submitted_at' => $complaint->created_at?->toIso8601String(),
                'attachments' => $complaint->attachments_count,
                'resolution_summary' => $complaint->resolution_summary,
                'updates' => $complaint->history->map(fn (ComplaintStatusChange $change): array => [
                    'status' => $change->to_status->label(),
                    'response' => $change->public_response,
                    'at' => $change->created_at->toIso8601String(),
                ]),
            ],
        ]);
    }
}
