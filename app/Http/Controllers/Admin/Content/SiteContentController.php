<?php

namespace App\Http\Controllers\Admin\Content;

use App\Enums\Access\Permission;
use App\Enums\Content\ContactStatus;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Content\Announcement;
use App\Models\Content\ContactMessage;
use App\Models\Content\Page;
use App\Services\Content\ContentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Smaller content types: announcements, static pages and the contact inbox.
 */
class SiteContentController extends Controller
{
    use AuthorizesAreas;

    public function announcements(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);

        return Inertia::render('admin/content/announcements', [
            'announcements' => Announcement::query()->with('community:id,name')->latest('starts_on')->latest('id')->paginate(25)
                ->through(fn (Announcement $announcement): array => [
                    ...$announcement->only(['id', 'title', 'body', 'community_id', 'is_published', 'is_urgent']),
                    'community' => $announcement->community?->name,
                    'starts_on' => $announcement->starts_on->toDateString(),
                    'ends_on' => $announcement->ends_on?->toDateString(),
                ]),
            'can' => ['publish' => $request->user()->can(Permission::ContentPublish->value)],
        ]);
    }

    public function saveAnnouncement(Request $request, ?Announcement $announcement = null): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:2000'],
            'community_id' => ['nullable', 'integer', Rule::exists('communities', 'id')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_published' => ['boolean'],
            'is_urgent' => ['boolean'],
        ]);

        if (($validated['is_published'] ?? false) && ! $request->user()->can(Permission::ContentPublish->value)) {
            $validated['is_published'] = false;
        }

        ($announcement ?? new Announcement)->fill($validated)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Announcement saved.')]);

        return back();
    }

    public function pages(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);

        $pages = Page::query()->get()->keyBy('slug');

        return Inertia::render('admin/content/pages', [
            'pages' => collect(Page::EDITABLE)->map(fn (string $title, string $slug): array => [
                'slug' => $slug,
                'title' => $pages[$slug]->title ?? $title,
                'body_markdown' => $pages[$slug]->body_markdown ?? '',
                'is_published' => (bool) ($pages[$slug]->is_published ?? false),
                'updated_at' => $pages[$slug]?->updated_at?->toIso8601String(),
            ])->values(),
            'can' => ['publish' => $request->user()->can(Permission::ContentPublish->value)],
        ]);
    }

    public function savePage(Request $request, string $slug, ContentRenderer $renderer): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::ContentPublish);
        abort_unless(array_key_exists($slug, Page::EDITABLE), 404);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body_markdown' => ['required', 'string', 'max:100000'],
            'is_published' => ['boolean'],
        ]);

        $page = Page::query()->firstOrNew(['slug' => $slug]);
        $page->fill($validated);
        $page->forceFill(['body_html' => $renderer->render($validated['body_markdown']), 'updated_by' => $request->user()->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Page saved.')]);

        return back();
    }

    public function inbox(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::ContactMessagesView);
        $status = ContactStatus::tryFrom($request->string('status')->toString());

        return Inertia::render('admin/content/inbox', [
            'messages' => ContactMessage::query()
                ->with('handler:id,name')
                ->when($status, fn ($query) => $query->where('status', $status))
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (ContactMessage $message): array => [
                    ...$message->only(['id', 'name', 'email', 'phone', 'subject', 'message', 'internal_note']),
                    'status' => $message->status->value,
                    'handled_by' => $message->handler?->name,
                    'created_at' => $message->created_at?->toIso8601String(),
                ]),
            'filters' => ['status' => $status?->value ?? ''],
            'statusOptions' => ContactStatus::options(),
        ]);
    }

    public function updateMessage(Request $request, ContactMessage $message): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::ContactMessagesView);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(ContactStatus::class)],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $message->forceFill([...$validated, 'handled_by' => $request->user()->id, 'handled_at' => now()])->save();

        return back();
    }
}
