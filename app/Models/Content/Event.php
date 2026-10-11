<?php

namespace App\Models\Content;

use App\Enums\Content\EventCategory;
use App\Enums\Content\EventStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\HasAreaPath;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $description_markdown
 * @property string $description_html
 * @property EventCategory $category
 * @property int|null $county_id
 * @property int|null $community_id
 * @property string|null $venue
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string|null $organizer
 * @property EventStatus $status
 * @property int|null $featured_media_id
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
#[Fillable(['title', 'slug', 'description_markdown', 'category', 'venue', 'starts_at', 'ends_at', 'organizer', 'featured_media_id'])]
class Event extends Model
{
    use AuditsActivity, HasAreaPath, TracksAuthors;

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
            'category' => EventCategory::class,
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereIn('status', [EventStatus::Published, EventStatus::Cancelled]);
    }

    /**
     * @return BelongsTo<MediaItem, $this>
     */
    public function featuredMedia(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class, 'featured_media_id');
    }
}
