<?php

namespace App\Models\Content;

use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\TracksAuthors;
use App\Models\Geography\Community;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property string $body
 * @property int|null $community_id
 * @property Carbon $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_published
 * @property bool $is_urgent
 */
#[Fillable(['title', 'body', 'community_id', 'starts_on', 'ends_on', 'is_published', 'is_urgent'])]
class Announcement extends Model
{
    use AuditsActivity, TracksAuthors;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_published' => 'boolean',
            'is_urgent' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function current(Builder $query): void
    {
        $today = now()->toDateString();

        $query->where('is_published', true)
            ->whereDate('starts_on', '<=', $today)
            ->where(fn (Builder $inner) => $inner->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today));
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}
