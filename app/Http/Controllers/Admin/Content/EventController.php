<?php

namespace App\Http\Controllers\Admin\Content;

use App\Enums\Access\Permission;
use App\Enums\Content\EventCategory;
use App\Enums\Content\EventStatus;
use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Content\Event;
use App\Services\Content\ContentRenderer;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Community events. Editors submit; publishers approve before an event
 * appears on the public website.
 */
class EventController extends Controller
{
    use AuthorizesAreas;

    public function index(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);
        $status = EventStatus::tryFrom($request->string('status')->toString());

        $events = Event::query()
            ->with(Event::areaRelations())
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('starts_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Event $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'status' => $event->status->value,
                'category' => $event->category->label(),
                'starts_at' => $event->starts_at->toIso8601String(),
                'area' => $event->county_id ? $event->areaLabel() : 'National',
            ]);

        return Inertia::render('admin/content/events/index', [
            'events' => $events,
            'filters' => ['status' => $status?->value ?? ''],
            'statusOptions' => EventStatus::options(),
            'can' => ['publish' => $request->user()->can(Permission::ContentPublish->value)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);

        return Inertia::render('admin/content/events/form', ['event' => null, 'categories' => EventCategory::options()]);
    }

    public function store(Request $request, ContentRenderer $renderer, GeoAreaResolver $resolver): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);
        $data = $this->validated($request);

        $event = new Event([...Arr::except($data, ['level', 'area_id']), 'slug' => $this->uniqueSlug($data['title'])]);
        $event->forceFill([...$this->path($data, $resolver), 'description_html' => $renderer->render($data['description_markdown']), 'status' => EventStatus::Pending])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event submitted for approval.')]);

        return to_route('admin.content.events.index');
    }

    public function edit(Request $request, Event $event): Response
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);
        $event->load(Event::areaRelations());

        return Inertia::render('admin/content/events/form', [
            'event' => [
                ...$event->only(['id', 'title', 'description_markdown', 'venue', 'organizer']),
                'category' => $event->category->value,
                'status' => $event->status->value,
                'starts_at' => $event->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $event->ends_at?->format('Y-m-d\TH:i'),
                'level' => $event->county_id ? $event->level()->value : null,
                'area_id' => $event->county_id ? $event->areaId() : null,
                'area' => $event->county_id ? $event->areaLabel() : 'National',
            ],
            'categories' => EventCategory::options(),
        ]);
    }

    public function update(Request $request, Event $event, ContentRenderer $renderer, GeoAreaResolver $resolver): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);
        $data = $this->validated($request);

        $event->fill(Arr::except($data, ['level', 'area_id']));
        $event->forceFill([...$this->path($data, $resolver), 'description_html' => $renderer->render($data['description_markdown'])]);

        // Edits to a published event by a non-publisher go back for approval.
        if ($event->status === EventStatus::Published && ! $request->user()->can(Permission::ContentPublish->value)) {
            $event->forceFill(['status' => EventStatus::Pending]);
        }

        $event->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Event saved.')]);

        return to_route('admin.content.events.index');
    }

    public function status(Request $request, Event $event): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::ContentPublish);

        $validated = $request->validate(['status' => ['required', Rule::enum(EventStatus::class)]]);
        $status = EventStatus::from($validated['status']);

        $event->forceFill([
            'status' => $status,
            'approved_by' => $status === EventStatus::Published ? $request->user()->id : $event->approved_by,
            'approved_at' => $status === EventStatus::Published ? now() : $event->approved_at,
        ])->save();

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description_markdown' => ['required', 'string', 'max:50000'],
            'category' => ['required', Rule::enum(EventCategory::class)],
            'venue' => ['nullable', 'string', 'max:255'],
            'organizer' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'level' => ['nullable', Rule::enum(GeoLevel::class), 'required_with:area_id'],
            'area_id' => ['nullable', 'integer', 'min:1'],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('media_items', 'id')],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|null>
     */
    private function path(array $data, GeoAreaResolver $resolver): array
    {
        $empty = ['county_id' => null, 'district_id' => null, 'clan_id' => null, 'town_id' => null, 'community_id' => null];

        if (empty($data['level']) || empty($data['area_id']) || $data['level'] === GeoLevel::Block->value) {
            return $empty;
        }

        $area = $resolver->find(GeoLevel::from($data['level']), (int) $data['area_id']);
        abort_if($area === null, 422, 'Unknown area.');

        return array_intersect_key($resolver->fullPath($area), $empty);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'event';
        $slug = $base;
        $suffix = 2;

        while (Event::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }
}
