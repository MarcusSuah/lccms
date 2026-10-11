<?php

namespace App\Http\Controllers\Public;

use App\Enums\Content\EventStatus;
use App\Enums\Facilities\FacilityCategory;
use App\Http\Controllers\Controller;
use App\Models\Content\Announcement;
use App\Models\Content\ContactMessage;
use App\Models\Content\Event;
use App\Models\Content\Page;
use App\Models\Content\Post;
use App\Models\Geography\Community;
use App\Services\Content\PublicStatistics;
use App\Services\Maps\MapLayers;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Honeypot\Honeypot;

class PublicSiteController extends Controller
{
    public function home(PublicStatistics $statistics): Response
    {
        $featured = Community::query()
            ->active()
            ->where('is_published', true)
            ->whereHas('media', fn (Builder $query) => $query->publiclyVisible())
            ->with(['town:id,name', 'county:id,name'])
            ->inRandomOrder()
            ->limit(6)
            ->get()
            ->map(function (Community $community): array {
                $photo = $community->media()->publiclyVisible()->orderByDesc('is_featured')->orderBy('sort_order')->first();

                return [
                    'name' => $community->name,
                    'slug' => $community->slug,
                    'location' => collect([$community->town?->name, $community->county?->name])->filter()->join(', '),
                    'photo' => $photo ? route('media.show', ['media' => $photo->uuid, 'variant' => 'thumb']) : null,
                ];
            });

        return Inertia::render('public/home', [
            'intro' => Page::query()->where('slug', 'about')->where('is_published', true)->first()?->only(['title', 'body_html']),
            'population' => $statistics->population(),
            'registry' => $statistics->registry(),
            'facilities' => $statistics->facilities(),
            'facilityCategories' => FacilityCategory::options(),
            'infrastructure' => $statistics->infrastructure(),
            'featured' => $featured,
            'events' => Event::query()
                ->where('status', EventStatus::Published)
                ->where('starts_at', '>=', now()->startOfDay())
                ->orderBy('starts_at')
                ->limit(4)
                ->get()
                ->map(fn (Event $event): array => ['title' => $event->title, 'slug' => $event->slug, 'starts_at' => $event->starts_at->toIso8601String(), 'venue' => $event->venue]),
            'posts' => Post::query()->live()->latest('published_at')->limit(3)->get(['title', 'slug', 'excerpt', 'published_at'])
                ->map(fn (Post $post): array => [...$post->only(['title', 'slug', 'excerpt']), 'published_at' => $post->published_at?->toIso8601String()]),
            'announcements' => Announcement::query()->current()->whereNull('community_id')->orderByDesc('is_urgent')->latest('starts_on')->limit(3)->get(['id', 'title', 'body', 'is_urgent']),
            'contact' => Page::query()->where('slug', 'contact')->where('is_published', true)->value('body_html'),
        ]);
    }

    public function about(): Response
    {
        $pages = Page::query()
            ->whereIn('slug', ['about', 'methodology', 'governance', 'data-protection'])
            ->where('is_published', true)
            ->get(['slug', 'title', 'body_html'])
            ->sortBy(fn (Page $page): int => array_search($page->slug, ['about', 'methodology', 'governance', 'data-protection'], true))
            ->values();

        return Inertia::render('public/about', [
            'pages' => $pages,
            'participatingCommunities' => Community::query()->active()->where('is_published', true)->count(),
        ]);
    }

    public function map(MapLayers $layers): Response
    {
        return Inertia::render('public/map', [
            'points' => Cache::remember('public-map-points', now()->addMinutes(10), fn (): array => $layers->publicPoints()),
            'tiles' => config('lccms.maps'),
        ]);
    }

    public function contact(Honeypot $honeypot): Response
    {
        return Inertia::render('public/contact', [
            'contact' => Page::query()->where('slug', 'contact')->where('is_published', true)->value('body_html'),
            'honeypot' => $honeypot->toArray(),
        ]);
    }

    public function sendContact(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ]);

        $message = new ContactMessage($validated);
        $message->forceFill(['ip_hash' => hash_hmac('sha256', (string) $request->ip(), (string) config('app.key'))])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Thank you. Your message has been received.')]);

        return back();
    }
}
