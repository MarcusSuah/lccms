<?php

namespace App\Http\Controllers\Public;

use App\Enums\Content\EventStatus;
use App\Http\Controllers\Controller;
use App\Models\Content\Event;
use App\Models\Content\Post;
use App\Models\Content\PostCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicNewsController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:120'],
            'tag' => ['nullable', 'string', 'max:80'],
        ]);

        $posts = Post::query()
            ->live()
            ->with(['category:id,name,slug', 'featuredMedia'])
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->whereFullText(['title', 'excerpt', 'body_markdown'], $search))
            ->when($validated['category'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('category', fn (Builder $category) => $category->where('slug', $slug)))
            ->when($validated['tag'] ?? null, fn (Builder $query, string $slug) => $query->whereHas('tags', fn (Builder $tag) => $tag->where('slug', $slug)))
            ->latest('published_at')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Post $post): array => [
                'title' => $post->title,
                'slug' => $post->slug,
                'excerpt' => $post->excerpt,
                'published_at' => $post->published_at?->toIso8601String(),
                'category' => $post->category?->only(['name', 'slug']),
                'image' => $post->featuredMedia?->isPubliclyVisible() ? route('media.show', ['media' => $post->featuredMedia->uuid, 'variant' => 'thumb']) : null,
            ]);

        return Inertia::render('public/news/index', [
            'posts' => $posts,
            'categories' => PostCategory::query()->orderBy('name')->get(['name', 'slug']),
            'filters' => array_merge(['search' => '', 'category' => '', 'tag' => ''], array_filter($validated)),
        ]);
    }

    public function show(Post $post): Response
    {
        abort_unless(Post::query()->live()->whereKey($post->id)->exists(), 404);

        $post->load(['author:id,name', 'category:id,name,slug', 'tags:id,name,slug', 'featuredMedia']);

        return Inertia::render('public/news/show', [
            'post' => [
                'title' => $post->title,
                'excerpt' => $post->excerpt,
                'body_html' => $post->body_html,
                'published_at' => $post->published_at?->toIso8601String(),
                'author' => $post->author?->name,
                'category' => $post->category?->only(['name', 'slug']),
                'tags' => $post->tags->map->only(['name', 'slug']),
                'image' => $post->featuredMedia?->isPubliclyVisible() ? [
                    'url' => route('media.show', ['media' => $post->featuredMedia->uuid, 'variant' => 'full']),
                    'alt' => $post->featuredMedia->alt_text ?: $post->title,
                ] : null,
                'seo_title' => $post->seo_title ?: $post->title,
                'seo_description' => $post->seo_description ?: $post->excerpt,
            ],
        ]);
    }

    public function events(Request $request): Response
    {
        $past = $request->boolean('past');

        $events = Event::query()
            ->published()
            ->with(['community:id,name,slug', 'county:id,name'])
            ->when($past, fn (Builder $query) => $query->where('starts_at', '<', now()->startOfDay())->orderByDesc('starts_at'))
            ->when(! $past, fn (Builder $query) => $query->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at'))
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Event $event): array => $this->summary($event));

        return Inertia::render('public/events/index', ['events' => $events, 'past' => $past]);
    }

    public function event(Event $event): Response
    {
        abort_unless(in_array($event->status, [EventStatus::Published, EventStatus::Cancelled], true), 404);
        $event->load(['community:id,name,slug', 'county:id,name', 'featuredMedia']);

        return Inertia::render('public/events/show', [
            'event' => [
                ...$this->summary($event),
                'description_html' => $event->description_html,
                'organizer' => $event->organizer,
                'ends_at' => $event->ends_at?->toIso8601String(),
                'image' => $event->featuredMedia?->isPubliclyVisible() ? route('media.show', ['media' => $event->featuredMedia->uuid, 'variant' => 'full']) : null,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Event $event): array
    {
        return [
            'title' => $event->title,
            'slug' => $event->slug,
            'category' => $event->category->label(),
            'status' => $event->status->value,
            'starts_at' => $event->starts_at->toIso8601String(),
            'venue' => $event->venue,
            'community' => $event->community?->only(['name', 'slug']),
            'county' => $event->county?->name,
        ];
    }
}
