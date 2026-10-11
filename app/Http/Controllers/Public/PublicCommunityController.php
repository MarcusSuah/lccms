<?php

namespace App\Http\Controllers\Public;

use App\Enums\Content\EventStatus;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Content\Announcement;
use App\Models\Content\Event;
use App\Models\Content\MediaItem;
use App\Models\Facilities\Facility;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use App\Models\Geography\County;
use App\Models\Infrastructure\InfrastructureAsset;
use App\Models\Leadership\LeadershipTenure;
use App\Services\Content\PublicStatistics;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public community directory and profiles. Only published communities and
 * verified, public-marked records appear. Individual census data never does.
 */
class PublicCommunityController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'county' => ['nullable', 'integer'],
        ]);

        $communities = Community::query()
            ->active()
            ->where('is_published', true)
            ->with(['town:id,name', 'district:id,name', 'county:id,name'])
            ->when($validated['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($validated['county'] ?? null, fn (Builder $query, int $county) => $query->where('county_id', $county))
            ->orderBy('name')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (Community $community): array => [
                'name' => $community->name,
                'slug' => $community->slug,
                'location' => collect([$community->town?->name, $community->district?->name, $community->county?->name])->filter()->join(', '),
                'photo' => $this->featuredPhoto($community),
            ]);

        return Inertia::render('public/communities/index', [
            'communities' => $communities,
            'counties' => County::query()->active()->orderBy('name')->get(['id', 'name'])->map(fn ($county): array => ['value' => $county->id, 'label' => $county->name]),
            'filters' => ['search' => $validated['search'] ?? '', 'county' => $validated['county'] ?? ''],
        ]);
    }

    public function show(Community $community, PublicStatistics $statistics): Response
    {
        abort_unless($community->is_published && $community->status === RecordStatus::Active, 404);

        $community->load(['county:id,name', 'district:id,name,district_number', 'clan:id,name', 'town:id,name']);
        $area = ['community_id' => $community->id];

        $facilities = Facility::query()
            ->where('community_id', $community->id)
            ->where('is_public', true)
            ->where('verification_status', VerificationStatus::Verified)
            ->where('status', '!=', RecordStatus::Archived)
            ->with('type:id,name')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $leaders = LeadershipTenure::query()
            ->where('community_id', $community->id)
            ->where('is_public', true)
            ->where('verification_status', VerificationStatus::Verified)
            ->with(['position:id,title,sort_order', 'block:id,name'])
            ->orderByDesc('starts_on')
            ->get();

        return Inertia::render('public/communities/show', [
            'community' => [
                'name' => $community->name,
                'slug' => $community->slug,
                'reference_code' => $community->reference_code,
                'description' => $community->description,
                'history' => $community->history,
                'public_email' => $community->public_email,
                'public_phone' => $community->public_phone,
                'verification_status' => $community->verification_status->value,
                'county' => $community->county?->name,
                'district' => $community->district?->name,
                'clan' => $community->clan?->name,
                'town' => $community->town?->name,
                'location' => $community->latitude !== null && $community->verification_status === VerificationStatus::Verified
                    ? ['latitude' => (float) $community->latitude, 'longitude' => (float) $community->longitude]
                    : null,
                'blocks' => Block::query()->where('community_id', $community->id)->active()->count(),
            ],
            'population' => $statistics->population($area),
            'facilities' => $facilities->map(fn (Facility $facility): array => [
                'name' => $facility->name,
                'type' => $facility->type?->name,
                'category' => $facility->category->value,
                'category_label' => $facility->category->label(),
                'ownership' => $facility->ownership->label(),
                'operating_status' => $facility->operating_status->label(),
            ]),
            'infrastructure' => InfrastructureAsset::query()
                ->where('community_id', $community->id)
                ->where('is_public', true)
                ->where('verification_status', VerificationStatus::Verified)
                ->where('status', '!=', RecordStatus::Archived)
                ->orderBy('category')
                ->get()
                ->map(fn (InfrastructureAsset $asset): array => [
                    'name' => $asset->name,
                    'category' => $asset->category->label(),
                    'condition' => $asset->condition->label(),
                    'availability' => $asset->availability->label(),
                ]),
            'leaders' => [
                'current' => $leaders->filter->isCurrent()->values()->map(fn (LeadershipTenure $tenure): array => $this->leader($tenure)),
                'former' => $leaders->reject->isCurrent()->values()->map(fn (LeadershipTenure $tenure): array => $this->leader($tenure)),
            ],
            'gallery' => $community->media()
                ->publiclyVisible()
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->limit(24)
                ->get()
                ->map(fn (MediaItem $item): array => [
                    'uuid' => $item->uuid,
                    'caption' => $item->caption,
                    'alt' => $item->alt_text ?: $item->caption ?: $community->name,
                    'thumbnail_url' => route('media.show', ['media' => $item->uuid, 'variant' => 'thumb']),
                    'url' => route('media.show', ['media' => $item->uuid, 'variant' => 'full']),
                ]),
            'events' => Event::query()
                ->where('status', EventStatus::Published)
                ->where('community_id', $community->id)
                ->where('starts_at', '>=', now()->startOfDay())
                ->orderBy('starts_at')
                ->limit(5)
                ->get()
                ->map(fn (Event $event): array => ['title' => $event->title, 'slug' => $event->slug, 'starts_at' => $event->starts_at->toIso8601String(), 'venue' => $event->venue]),
            'announcements' => Announcement::query()
                ->current()
                ->where('community_id', $community->id)
                ->latest('starts_on')
                ->limit(5)
                ->get(['id', 'title', 'body', 'is_urgent', 'starts_on'])
                ->map(fn (Announcement $announcement): array => [...$announcement->only(['id', 'title', 'body', 'is_urgent']), 'starts_on' => $announcement->starts_on->toDateString()]),
        ]);
    }

    public function leaders(Request $request): Response
    {
        $search = $request->string('search')->trim()->toString();

        $tenures = LeadershipTenure::query()
            ->current()
            ->where('is_public', true)
            ->where('verification_status', VerificationStatus::Verified)
            ->whereHas('community', fn (Builder $query) => $query->where('is_published', true))
            ->with(['position:id,title', 'community:id,name,slug', 'block:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('holder_name', 'like', "%{$search}%")
                ->orWhereHas('community', fn (Builder $community) => $community->where('name', 'like', "%{$search}%"))))
            ->orderBy('community_id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (LeadershipTenure $tenure): array => [
                ...$this->leader($tenure),
                'community' => $tenure->community?->name,
                'community_slug' => $tenure->community?->slug,
            ]);

        return Inertia::render('public/leaders', ['tenures' => $tenures, 'filters' => ['search' => $search]]);
    }

    /**
     * Public leadership data: name, title, area and dates only — never contacts.
     *
     * @return array<string, mixed>
     */
    private function leader(LeadershipTenure $tenure): array
    {
        return [
            'name' => $tenure->holder_name,
            'position' => $tenure->position?->title,
            'block' => $tenure->block?->name,
            'starts_on' => $tenure->starts_on->toDateString(),
            'ends_on' => $tenure->ends_on?->toDateString(),
            'selection_method' => $tenure->selection_method->label(),
        ];
    }

    /**
     * @return array{thumbnail_url: string, alt: string}|null
     */
    private function featuredPhoto(Community $community): ?array
    {
        $item = $community->media()->publiclyVisible()->orderByDesc('is_featured')->orderBy('sort_order')->first();

        return $item === null ? null : [
            'thumbnail_url' => route('media.show', ['media' => $item->uuid, 'variant' => 'thumb']),
            'alt' => $item->alt_text ?: $community->name,
        ];
    }
}
