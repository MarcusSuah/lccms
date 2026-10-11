<?php

namespace App\Models\Geography;

use App\Enums\Geography\GeoLevel;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Concerns\AuditsActivity;
use App\Models\Concerns\GeoScoped;
use App\Models\Concerns\TracksAuthors;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Shared behaviour of the six administrative levels.
 *
 * @property int $id
 * @property string $reference_code
 * @property string $name
 * @property string $normalized_name
 * @property string|null $description
 * @property RecordStatus $status
 * @property VerificationStatus $verification_status
 * @property string|null $latitude
 * @property string|null $longitude
 * @property array<string, mixed>|null $boundary
 * @property bool $is_fixture
 * @property int|null $county_id
 * @property int|null $district_id
 * @property int|null $clan_id
 * @property int|null $town_id
 * @property int|null $community_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
abstract class GeoArea extends Model
{
    use AuditsActivity, GeoScoped, HasFactory, TracksAuthors;

    abstract public static function level(): GeoLevel;

    /**
     * @return list<string>
     */
    public function getFillable(): array
    {
        $ancestorColumns = array_map(
            fn (GeoLevel $level): string => $level->column(),
            array_slice(static::level()->lineage(), 0, -1),
        );

        return [
            ...$ancestorColumns,
            ...$this->levelFillable(),
            'reference_code',
            'name',
            'description',
            'status',
            'verification_status',
            'latitude',
            'longitude',
            'boundary',
            'is_fixture',
        ];
    }

    /**
     * Additional mass-assignable attributes specific to a level.
     *
     * @return list<string>
     */
    protected function levelFillable(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'verification_status' => VerificationStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'boundary' => 'array',
            'is_fixture' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (GeoArea $area): void {
            $area->normalized_name = static::normalizeName($area->name);
        });
    }

    /**
     * Canonical form used for duplicate detection and per-parent uniqueness.
     */
    public static function normalizeName(string $name): string
    {
        return Str::of($name)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9#]+/', ' ')
            ->squish()
            ->toString();
    }

    /**
     * @return array<string, string>
     */
    public static function geoColumns(): array
    {
        $columns = [];

        foreach (static::level()->lineage() as $level) {
            $columns[$level->value] = $level === static::level() ? 'id' : $level->column();
        }

        return $columns;
    }

    /**
     * Ancestor ids plus this area's own id, keyed by level column. Used to fill
     * the denormalised path on children and on census records.
     *
     * @return array<string, int>
     */
    public function pathAttributes(): array
    {
        $path = [];

        foreach (static::level()->lineage() as $level) {
            $path[$level->column()] = $level === static::level()
                ? $this->id
                : $this->getAttribute($level->column());
        }

        return $path;
    }

    /**
     * The parent area, or null for counties.
     */
    public function parentArea(): ?GeoArea
    {
        $parentLevel = static::level()->parent();

        if ($parentLevel === null) {
            return null;
        }

        /** @var GeoArea|null $parent */
        $parent = $this->getRelationValue($parentLevel->value);

        return $parent;
    }

    /**
     * The chain of areas from the county down to this one.
     *
     * @return list<GeoArea>
     */
    public function breadcrumb(): array
    {
        $trail = [];

        foreach (static::level()->lineage() as $level) {
            if ($level === static::level()) {
                $trail[] = $this;

                continue;
            }

            /** @var GeoArea|null $ancestor */
            $ancestor = $this->getRelationValue($level->value);

            if ($ancestor !== null) {
                $trail[] = $ancestor;
            }
        }

        return $trail;
    }

    /**
     * Relations to eager load to render a breadcrumb without extra queries.
     *
     * @return list<string>
     */
    public static function ancestorRelations(): array
    {
        return array_map(
            fn (GeoLevel $level): string => $level->value,
            array_slice(static::level()->lineage(), 0, -1),
        );
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', RecordStatus::Active);
    }

    /**
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        if ($term === null || trim($term) === '') {
            return;
        }

        $normalized = static::normalizeName($term);

        $query->where(function (Builder $inner) use ($term, $normalized): void {
            $inner->where('normalized_name', 'like', '%'.$normalized.'%')
                ->orWhere('reference_code', 'like', '%'.trim($term).'%');
        });
    }

    /**
     * @return BelongsTo<County, $this>
     */
    public function county(): BelongsTo
    {
        return $this->belongsTo(County::class);
    }

    /**
     * @return BelongsTo<District, $this>
     */
    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    /**
     * @return BelongsTo<Clan, $this>
     */
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    /**
     * @return BelongsTo<Town, $this>
     */
    public function town(): BelongsTo
    {
        return $this->belongsTo(Town::class);
    }

    /**
     * @return BelongsTo<Community, $this>
     */
    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    /**
     * Direct children of this area.
     *
     * @return HasMany<GeoArea, $this>
     */
    public function children(): HasMany
    {
        $childLevel = static::level()->child();

        abort_if($childLevel === null, 500, 'Blocks have no geographic children.');

        return $this->hasMany($childLevel->modelClass(), static::level()->column());
    }
}
