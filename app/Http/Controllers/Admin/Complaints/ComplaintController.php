<?php

namespace App\Http\Controllers\Admin\Complaints;

use App\Actions\Complaints\ManageComplaint;
use App\Enums\Access\Permission;
use App\Enums\Complaints\ComplaintCategory;
use App\Enums\Complaints\ComplaintPriority;
use App\Enums\Complaints\ComplaintStatus;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Complaints\Complaint;
use App\Models\Complaints\ComplaintAttachment;
use App\Models\Complaints\ComplaintNote;
use App\Models\Complaints\ComplaintStatusChange;
use App\Models\User;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplaintController extends Controller
{
    use AuthorizesAreas;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::ComplaintsView);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:40'],
            'status' => ['nullable', Rule::enum(ComplaintStatus::class)],
            'category' => ['nullable', Rule::enum(ComplaintCategory::class)],
            'mine' => ['nullable', 'boolean'],
        ]);

        $complaints = Complaint::query()
            ->visibleTo($user)
            ->with(['assignee:id,name', 'community:id,name', 'county:id,name'])
            ->withCount('attachments')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(fn (Builder $inner) => $inner->where('public_reference', 'like', "%{$search}%")->orWhere('subject', 'like', "%{$search}%")))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->where('category', $category))
            ->when($filters['mine'] ?? false, fn (Builder $query) => $query->where('assigned_to', $user->id))
            ->orderByRaw("FIELD(priority, 'urgent', 'high', 'normal', 'low')")
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Complaint $complaint): array => [
                'reference' => $complaint->public_reference,
                'subject' => $complaint->subject,
                'category' => $complaint->category->label(),
                'status' => $complaint->status->value,
                'priority' => $complaint->priority->value,
                'area' => collect([$complaint->community?->name, $complaint->county?->name])->filter()->join(', '),
                'assignee' => $complaint->assignee?->name,
                'attachments' => $complaint->attachments_count,
                'created_at' => $complaint->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/complaints/index', [
            'complaints' => $complaints,
            'filters' => array_merge(['search' => '', 'status' => '', 'category' => '', 'mine' => ''], $filters),
            'statusOptions' => ComplaintStatus::options(),
            'categoryOptions' => ComplaintCategory::options(),
        ]);
    }

    public function show(Request $request, Complaint $complaint, GeoScope $geoScope): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::ComplaintsView, $complaint);

        $complaint->load(['assignee:id,name', 'county:id,name', 'district:id,name', 'clan:id,name', 'town:id,name', 'community:id,name', 'block:id,name', 'history.actor:id,name', 'notes.author:id,name', 'attachments']);

        $officers = $user->can(Permission::ComplaintsAssign->value)
            ? User::query()->where('is_active', true)->permission(Permission::ComplaintsManage->value)->orderBy('name')->get(['id', 'name'])
                ->filter(fn (User $officer): bool => $geoScope->allows($officer, $complaint))
                ->map(fn (User $officer): array => ['value' => $officer->id, 'label' => $officer->name])
                ->values()
            : collect();

        return Inertia::render('admin/complaints/show', [
            'complaint' => [
                'reference' => $complaint->public_reference,
                'subject' => $complaint->subject,
                'category' => $complaint->category->label(),
                'description' => $complaint->description,
                'status' => $complaint->status->value,
                'priority' => $complaint->priority->value,
                'location' => collect([$complaint->county, $complaint->district, $complaint->clan, $complaint->town, $complaint->community, $complaint->block])->filter()->pluck('name')->all(),
                'house_reference' => $complaint->house_reference,
                'is_anonymous' => $complaint->is_anonymous,
                'complainant' => $complaint->is_anonymous ? null : [
                    'name' => $complaint->complainant_name,
                    'phone' => $complaint->complainant_phone,
                    'email' => $complaint->complainant_email,
                ],
                'assignee' => $complaint->assignee?->only(['id', 'name']),
                'resolution_summary' => $complaint->resolution_summary,
                'created_at' => $complaint->created_at?->toIso8601String(),
                'allowed_transitions' => array_map(fn (ComplaintStatus $status): array => ['value' => $status->value, 'label' => $status->label()], $complaint->status->allowedTransitions()),
                'attachments' => $complaint->attachments->map(fn (ComplaintAttachment $attachment): array => [
                    'slot' => $attachment->slot,
                    'thumbnail_url' => route('admin.complaints.attachments.show', ['complaint' => $complaint->public_reference, 'slot' => $attachment->slot, 'variant' => 'thumb']),
                    'url' => route('admin.complaints.attachments.show', ['complaint' => $complaint->public_reference, 'slot' => $attachment->slot, 'variant' => 'full']),
                ]),
                'history' => $complaint->history->map(fn (ComplaintStatusChange $change): array => [
                    'from' => $change->from_status?->label(),
                    'to' => $change->to_status->label(),
                    'public_response' => $change->public_response,
                    'actor' => $change->actor?->name ?? 'Public submission',
                    'at' => $change->created_at->toIso8601String(),
                ]),
                'notes' => $complaint->notes->map(fn (ComplaintNote $note): array => [
                    'id' => $note->id,
                    'body' => $note->body,
                    'author' => $note->author?->name,
                    'at' => $note->created_at->toIso8601String(),
                ]),
            ],
            'officers' => $officers,
            'priorityOptions' => ComplaintPriority::options(),
            'can' => [
                'manage' => $user->can(Permission::ComplaintsManage->value),
                'assign' => $user->can(Permission::ComplaintsAssign->value),
            ],
        ]);
    }

    public function update(Request $request, Complaint $complaint, ManageComplaint $manage): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::ComplaintsManage, $complaint);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
            'public_response' => ['nullable', 'string', 'max:3000'],
            'resolution_summary' => ['nullable', 'string', 'max:3000'],
        ]);

        $manage->transition($user, $complaint, ComplaintStatus::from($validated['status']), $validated['public_response'] ?? null, $validated['resolution_summary'] ?? null);

        return back();
    }

    public function assign(Request $request, Complaint $complaint, ManageComplaint $manage): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::ComplaintsAssign, $complaint);

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer', Rule::exists('users', 'id')],
            'priority' => ['nullable', Rule::enum(ComplaintPriority::class)],
        ]);

        if (isset($validated['priority'])) {
            $complaint->forceFill(['priority' => $validated['priority']])->save();
        }

        $manage->assign($user, $complaint, User::query()->findOrFail($validated['assigned_to']));

        return back();
    }

    public function note(Request $request, Complaint $complaint, ManageComplaint $manage): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::ComplaintsManage, $complaint);

        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $manage->note($user, $complaint, $validated['body']);

        return back();
    }

    public function attachment(Request $request, Complaint $complaint, int $slot, string $variant = 'full'): StreamedResponse
    {
        $this->authorizeArea($request->user(), Permission::ComplaintsView, $complaint);
        abort_unless(in_array($variant, ['full', 'thumb'], true), 404);

        $attachment = $complaint->attachments()->where('slot', $slot)->firstOrFail();
        $path = $variant === 'thumb' ? $attachment->thumbnail_path : $attachment->path;

        return Storage::disk($attachment->disk)->response($path, null, [
            'Content-Type' => $attachment->mime,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline',
        ]);
    }
}
