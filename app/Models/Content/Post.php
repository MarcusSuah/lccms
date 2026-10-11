<?php

namespace App\Models\Content;

use App\Enums\Content\PostStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Geography\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $excerpt
 * @property string $body_markdown
 * @property string $body_html
 * @property PostStatus $status
 * @property Carbon|null $published_at
 * @property int $author_id
 * @property int|null $reviewer_id
 * @property int|null $category_id
 * @property int|null $featured_media_id
 * @property int|null $community_id
 * @property string|null $seo_title
 * @property string|null $seo_description
 */
#[Fillable(['title', 'slug', 'excerpt', 'body_markdown', 'category_id', 'featured_media_id', 'community_id', 'seo_title', 'seo_description'])]
class Post extends Model
{
    use AuditsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Published, or scheduled with a publication time that has passed.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->whereIn('status', [PostStatus::Published, PostStatus::Scheduled])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<PostCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'featured_media_id');
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }
}
