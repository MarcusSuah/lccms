<?php

namespace App\Models\Geography;

use App\Enums\Geography\GeoLevel;
use App\Models\Content\MediaItem;
use Database\Factories\Geography\CommunityFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * @property string $slug
 * @property string|null $history
 * @property string|null $public_email
 * @property string|null $public_phone
 * @property bool $is_published
 */
#[UseFactory(CommunityFactory::class)]
class Community extends GeoArea
{
    protected $table = 'communities';

    public static function level(): GeoLevel
    {
        return GeoLevel::Community;
    }

    protected function levelFillable(): array
    {
        return ['slug', 'history', 'public_email', 'public_phone', 'is_published'];
    }

    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (Community $community): void {
            if (blank($community->slug)) {
                $community->slug = static::uniqueSlug($community->name);
            }
        });
    }

    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'community';
        $slug = $base;
        $suffix = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @return MorphMany<MediaItem, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(MediaItem::class, 'mediable');
    }

    /**
     * @return HasMany<Block, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }
}
