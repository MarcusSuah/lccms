<?php

namespace App\Http\Controllers\Admin\Content;

use App\Enums\Access\Permission;
use App\Enums\Content\ConsentStatus;
use App\Enums\Content\MediaApproval;
use App\Enums\Content\MediaVisibility;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Content\Event;
use App\Models\Content\MediaItem;
use App\Models\Content\Post;
use App\Models\Facilities\Facility;
use App\Models\Geography\Community;
use App\Models\Infrastructure\InfrastructureAsset;
use App\Services\Media\ImageProcessor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Photograph library. Uploads start as pending and staff-only; a person with
 * media.approve publishes them after checking consent. Photographs showing
 * children or identifiable people need recorded consent before publication.
 */
class MediaController extends Controller
{
    use AuthorizesAreas;

    /**
     * owner key => [model class, permission that may attach media]
     *
     * @var array<string, array{0: class-string<Model>, 1: Permission}>
     */
    private const OWNERS = [
        'community' => [Community::class, Permission::ContentManage],
        'facility' => [Facility::class, Permission::FacilitiesManage],
        'infrastructure' => [InfrastructureAsset::class, Permission::InfrastructureManage],
        'event' => [Event::class, Permission::ContentManage],
        'post' => [Post::class, Permission::ContentManage],
    ];

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, [Permission::ContentManage, Permission::MediaApprove]);

        $status = MediaApproval::tryFrom($request->string('status')->toString()) ?? MediaApproval::Pending;

        $media = MediaItem::query()
            ->where('approval_status', $status)
            ->with(['uploader:id,name', 'mediable'])
            ->latest('id')
            ->paginate(24)
            ->withQueryString()
            ->through(fn (MediaItem $item): array => $this->present($item));

        return Inertia::render('admin/content/media', [
            'media' => $media,
            'filters' => ['status' => $status->value],
            'statusOptions' => MediaApproval::options(),
            'consentOptions' => ConsentStatus::options(),
            'visibilityOptions' => MediaVisibility::options(),
            'can' => ['approve' => $user->can(Permission::MediaApprove->value)],
        ]);
    }

    public function store(Request $request, ImageProcessor $images): RedirectResponse
    {
        $validated = $request->validate([
            'owner_type' => ['required', Rule::in(array_keys(self::OWNERS))],
            'owner_id' => ['required', 'integer'],
            'image' => ['required', 'file', 'max:'.config('lccms.uploads.image_max_kilobytes'), 'mimes:jpg,jpeg,png,webp'],
            'caption' => ['nullable', 'string', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::enum(MediaVisibility::class)],
            'consent_status' => ['required', Rule::enum(ConsentStatus::class)],
            'shows_identifiable_people' => ['boolean'],
            'shows_children' => ['boolean'],
        ]);

        $owner = $this->owner($request, $validated['owner_type'], (int) $validated['owner_id']);
        $stored = $images->store($request->file('image'), 'media/'.$validated['owner_type']);

        $item = new MediaItem(collect($validated)->only(['caption', 'alt_text', 'visibility', 'consent_status', 'shows_identifiable_people', 'shows_children'])->all());
        $item->forceFill([
            ...$stored,
            'uuid' => (string) Str::uuid(),
            'mediable_type' => $owner->getMorphClass(),
            'mediable_id' => $owner->getKey(),
            'uploaded_by' => $request->user()->id,
            'sort_order' => MediaItem::query()->where('mediable_type', $owner->getMorphClass())->where('mediable_id', $owner->getKey())->count(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Photo uploaded. It will appear publicly once approved.')]);

        return back();
    }

    public function update(Request $request, MediaItem $media): RedirectResponse
    {
        $this->authorizeOwner($request, $media);

        $validated = $request->validate([
            'caption' => ['nullable', 'string', 'max:500'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'visibility' => ['required', Rule::enum(MediaVisibility::class)],
            'consent_status' => ['required', Rule::enum(ConsentStatus::class)],
            'shows_identifiable_people' => ['boolean'],
            'shows_children' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
        ]);

        DB::transaction(function () use ($media, $validated): void {
            if (! empty($validated['is_featured'])) {
                MediaItem::query()
                    ->where('mediable_type', $media->mediable_type)
                    ->where('mediable_id', $media->mediable_id)
                    ->whereKeyNot($media->id)
                    ->update(['is_featured' => false]);
            }

            $media->fill($validated);

            // Changing who is shown or the consent position needs a fresh approval.
            if ($media->isDirty(['visibility', 'consent_status', 'shows_identifiable_people', 'shows_children'])
                && $media->approval_status === MediaApproval::Approved) {
                $media->forceFill(['approval_status' => MediaApproval::Pending, 'approved_by' => null, 'approved_at' => null]);
            }

            $media->save();
        });

        return back();
    }

    public function approve(Request $request, MediaItem $media): RedirectResponse
    {
        abort_unless($request->user()->can(Permission::MediaApprove->value), 403);

        $validated = $request->validate(['decision' => ['required', Rule::in(['approved', 'rejected'])]]);

        if ($validated['decision'] === 'approved' && $media->visibility === MediaVisibility::Public) {
            $needsConsent = $media->shows_identifiable_people || $media->shows_children;

            if ($needsConsent && $media->consent_status !== ConsentStatus::Obtained) {
                throw ValidationException::withMessages(['decision' => 'Record consent before publishing a photo that shows identifiable people or children.']);
            }
        }

        $media->forceFill([
            'approval_status' => $validated['decision'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ])->save();

        return back();
    }

    public function destroy(Request $request, MediaItem $media): RedirectResponse
    {
        $this->authorizeOwner($request, $media);

        Storage::disk($media->disk)->delete([$media->path, $media->thumbnail_path]);
        $media->delete();

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Photo removed.')]);

        return back();
    }

    private function owner(Request $request, string $type, int $id): Model
    {
        [$class, $permission] = self::OWNERS[$type];
        $owner = $class::query()->findOrFail($id);

        $path = match (true) {
            $owner instanceof Community => $owner->pathAttributes(),
            method_exists($owner, 'geoPath') => $owner->geoPath(),
            method_exists($owner, 'path') => array_filter($owner->path()),
            default => null,
        };

        $this->authorizeArea($request->user(), $permission, $path === [] ? null : $path);

        return $owner;
    }

    private function authorizeOwner(Request $request, MediaItem $media): void
    {
        $type = collect(self::OWNERS)->search(fn (array $owner): bool => (new $owner[0])->getMorphClass() === $media->mediable_type);

        abort_if($type === false, 404);

        $this->owner($request, $type, (int) $media->mediable_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(MediaItem $item): array
    {
        return [
            'uuid' => $item->uuid,
            'caption' => $item->caption,
            'alt_text' => $item->alt_text,
            'owner' => $item->mediable?->getAttribute('name') ?? $item->mediable?->getAttribute('title'),
            'visibility' => $item->visibility->value,
            'approval_status' => $item->approval_status->value,
            'consent_status' => $item->consent_status->value,
            'shows_identifiable_people' => $item->shows_identifiable_people,
            'shows_children' => $item->shows_children,
            'is_featured' => $item->is_featured,
            'uploaded_by' => $item->uploader?->name,
            'thumbnail_url' => route('media.show', ['media' => $item->uuid, 'variant' => 'thumb']),
            'url' => route('media.show', ['media' => $item->uuid, 'variant' => 'full']),
        ];
    }
}
