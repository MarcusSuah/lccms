<?php

namespace App\Models\Content;

use App\Enums\Content\ConsentStatus;
use App\Enums\Content\MediaApproval;
use App\Enums\Content\MediaVisibility;
use App\Models\Concerns\AuditsActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string|null $mediable_type
 * @property int|null $mediable_id
 * @property string $disk
 * @property string $path
 * @property string $thumbnail_path
 * @property string $mime
 * @property int $size
 * @property int $width
 * @property int $height
 * @property string $sha256
 * @property string|null $original_name
 * @property string|null $caption
 * @property string|null $alt_text
 * @property int $sort_order
 * @property bool $is_featured
 * @property MediaVisibility $visibility
 * @property MediaApproval $approval_status
 * @property ConsentStatus $consent_status
 * @property bool $shows_identifiable_people
 * @property bool $shows_children
 * @property int|null $uploaded_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 */
#[Fillable(['caption', 'alt_text', 'sort_order', 'is_featured', 'visibility', 'consent_status', 'shows_identifiable_people', 'shows_children'])]
class MediaItem extends Model
{
    use AuditsActivity;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'visibility' => 'internal',
        'approval_status' => 'pending',
        'consent_status' => 'not_required',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => MediaVisibility::class,
            'approval_status' => MediaApproval::class,
            'consent_status' => ConsentStatus::class,
            'is_featured' => 'boolean',
            'shows_identifiable_people' => 'boolean',
            'shows_children' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /**
     * A photograph is publishable only when approved, marked public and, if it
     * shows identifiable people, consent has been obtained.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->approval_status === MediaApproval::Approved
            && $this->visibility === MediaVisibility::Public
            && in_array($this->consent_status, [ConsentStatus::NotRequired, ConsentStatus::Obtained], true)
            && ! ($this->shows_identifiable_people && $this->consent_status === ConsentStatus::NotRequired);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function publiclyVisible(Builder $query): void
    {
        $query->where('approval_status', MediaApproval::Approved)
            ->where('visibility', MediaVisibility::Public)
            ->where(fn (Builder $consent) => $consent
                ->where('consent_status', ConsentStatus::Obtained)
                ->orWhere(fn (Builder $none) => $none->where('consent_status', ConsentStatus::NotRequired)->where('shows_identifiable_people', false)));
    }

    /**
     * Shape used by the staff photo manager.
     *
     * @return array<string, mixed>
     */
    public function toManagerArray(): array
    {
        return [
            'uuid' => $this->uuid,
            'caption' => $this->caption,
            'alt_text' => $this->alt_text,
            'visibility' => $this->visibility->value,
            'approval_status' => $this->approval_status->value,
            'consent_status' => $this->consent_status->value,
            'shows_identifiable_people' => $this->shows_identifiable_people,
            'shows_children' => $this->shows_children,
            'is_featured' => $this->is_featured,
            'thumbnail_url' => route('media.show', ['media' => $this->uuid, 'variant' => 'thumb']),
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
