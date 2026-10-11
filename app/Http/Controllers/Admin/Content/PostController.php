<?php

namespace App\Http\Controllers\Admin\Content;

use App\Enums\Access\Permission;
use App\Enums\Content\PostStatus;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Content\MediaItem;
use App\Models\Content\Post;
use App\Models\Content\PostCategory;
use App\Models\Content\Tag;
use App\Services\Content\ContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * News and blog posts: draft → in review → published (now or scheduled).
 * Only content.publish holders may publish.
 */
class PostController extends Controller
{
    use AuthorizesAreas;

    public function index(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);

        $status = PostStatus::tryFrom($request->string('status')->toString());
        $search = $request->string('search')->trim()->toString();

        $posts = Post::query()
            ->with(['author:id,name', 'category:id,name'])
            ->when($status, fn (Builder $query) => $query->where('status', $status))
            ->when($search !== '', fn (Builder $query) => $query->where('title', 'like', "%{$search}%"))
            ->latest('updated_at')
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Post $post): array => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'status' => $post->status->value,
                'published_at' => $post->published_at?->toIso8601String(),
                'author' => $post->author?->name,
                'category' => $post->category?->name,
            ]);

        return Inertia::render('admin/content/posts/index', [
            'posts' => $posts,
            'filters' => ['status' => $status?->value ?? '', 'search' => $search],
            'statusOptions' => PostStatus::options(),
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);

        return Inertia::render('admin/content/posts/form', ['post' => null, ...$this->options($request)]);
    }

    public function store(Request $request, ContentRenderer $renderer): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);
        $data = $this->validated($request);

        $post = DB::transaction(function () use ($request, $data, $renderer): Post {
            $post = new Post([...$data, 'slug' => $this->uniqueSlug($data['slug'] ?? $data['title'])]);
            $post->forceFill([
                'author_id' => $request->user()->id,
                'body_html' => $renderer->render($data['body_markdown']),
                'excerpt' => $data['excerpt'] ?? $renderer->excerpt($data['body_markdown']),
            ])->save();
            $this->syncTags($post, $request->input('tags', ''));

            return $post;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft saved.')]);

        return to_route('admin.content.posts.edit', $post);
    }

    public function edit(Request $request, Post $post): Response
    {
        $this->authorizeArea($request->user(), Permission::ContentManage);
        $post->load('tags');

        return Inertia::render('admin/content/posts/form', [
            'post' => [
                ...$post->only(['id', 'title', 'slug', 'excerpt', 'body_markdown', 'body_html', 'category_id', 'featured_media_id', 'seo_title', 'seo_description']),
                'status' => $post->status->value,
                'published_at' => $post->published_at?->format('Y-m-d\TH:i'),
                'tags' => $post->tags->pluck('name')->join(', '),
            ],
            ...$this->options($request),
        ]);
    }

    public function update(Request $request, Post $post, ContentRenderer $renderer): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::ContentManage);

        if ($post->status === PostStatus::Published && ! $user->can(Permission::ContentPublish->value)) {
            abort(403, 'Only publishers can change a published post.');
        }

        $data = $this->validated($request, $post);

        DB::transaction(function () use ($request, $post, $data, $renderer): void {
            $post->fill([...$data, 'slug' => $data['slug'] === $post->slug ? $post->slug : $this->uniqueSlug($data['slug'] ?? $data['title'], $post->id)]);
            $post->forceFill([
                'body_html' => $renderer->render($data['body_markdown']),
                'excerpt' => $data['excerpt'] ?? $renderer->excerpt($data['body_markdown']),
            ])->save();
            $this->syncTags($post, $request->input('tags', ''));
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post saved.')]);

        return back();
    }

    public function transition(Request $request, Post $post): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::ContentManage);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['submit', 'publish', 'schedule', 'unpublish', 'archive'])],
            'published_at' => ['nullable', 'required_if:action,schedule', 'date', 'after:now'],
        ]);

        $publishing = in_array($validated['action'], ['publish', 'schedule', 'unpublish', 'archive'], true);
        abort_if($publishing && ! $user->can(Permission::ContentPublish->value), 403, 'Only publishers can do this.');

        if ($validated['action'] === 'submit' && ! in_array($post->status, [PostStatus::Draft], true)) {
            throw ValidationException::withMessages(['action' => 'Only drafts can be submitted for review.']);
        }

        $changes = match ($validated['action']) {
            'submit' => ['status' => PostStatus::InReview],
            'publish' => ['status' => PostStatus::Published, 'published_at' => $post->published_at?->isPast() ? $post->published_at : now(), 'reviewer_id' => $user->id],
            'schedule' => ['status' => PostStatus::Scheduled, 'published_at' => Carbon::parse($validated['published_at']), 'reviewer_id' => $user->id],
            'unpublish' => ['status' => PostStatus::Draft],
            'archive' => ['status' => PostStatus::Archived],
        };

        $post->forceFill($changes)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Post is now :status.', ['status' => $post->status->label()])]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Post $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'alpha_dash', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body_markdown' => ['required', 'string', 'max:100000'],
            'category_id' => ['nullable', 'integer', Rule::exists('post_categories', 'id')],
            'featured_media_id' => ['nullable', 'integer', Rule::exists('media_items', 'id')],
            'community_id' => ['nullable', 'integer', Rule::exists('communities', 'id')],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'seo_description' => ['nullable', 'string', 'max:170'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        unset($data['tags']);

        return $data;
    }

    private function uniqueSlug(string $value, ?int $ignore = null): string
    {
        $base = Str::slug($value) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (Post::query()->where('slug', $slug)->when($ignore, fn (Builder $query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    private function syncTags(Post $post, string $tags): void
    {
        $ids = collect(explode(',', $tags))
            ->map(fn (string $tag): string => trim($tag))
            ->filter()
            ->unique(fn (string $tag): string => Str::slug($tag))
            ->take(15)
            ->map(fn (string $tag): int => Tag::query()->firstOrCreate(['slug' => Str::slug($tag)], ['name' => Str::limit($tag, 60, '')])->id);

        $post->tags()->sync($ids->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function options(Request $request): array
    {
        return [
            'categories' => PostCategory::query()->orderBy('name')->get(['id', 'name'])->map(fn ($category): array => ['value' => $category->id, 'label' => $category->name]),
            'media' => MediaItem::query()->publiclyVisible()->latest('id')->limit(60)->get()->map(fn (MediaItem $item): array => [
                'value' => $item->id,
                'label' => $item->caption ?: $item->original_name ?: 'Photo '.$item->id,
                'thumbnail_url' => route('media.show', ['media' => $item->uuid, 'variant' => 'thumb']),
            ]),
            'can' => ['publish' => $request->user()->can(Permission::ContentPublish->value)],
        ];
    }
}
