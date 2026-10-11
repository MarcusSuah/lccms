<?php

namespace App\Services\Maps;

use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Census\House;
use App\Models\Facilities\Facility;
use App\Models\Geography\Community;
use App\Models\Infrastructure\InfrastructureAsset;
use App\Models\User;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Point layers for the staff and public maps.
 *
 * Staff layers follow the same permissions and geographic reach as the rest
 * of the system. House locations identify where people live, so they are
 * limited to users who may see census personal data. The public map shows
 * only published, verified communities and public, verified facilities;
 * houses and unverified locations never appear there.
 */
class MapLayers
{
    public function __construct(private GeoScope $geoScope) {}

    /**
     * @return list<array{key: string, label: string}>
     */
    public function availableTo(User $user): array
    {
        $layers = [
            'communities' => ['Communities', Permission::GeographyView],
            'facilities' => ['Facilities', Permission::FacilitiesView],
            'infrastructure' => ['Infrastructure', Permission::InfrastructureView],
            'houses' => ['Houses (census)', Permission::CensusViewPersonalData],
        ];

        $available = [];

        foreach ($layers as $key => [$label, $permission]) {
            if ($user->can($permission->value)) {
                $available[] = ['key' => $key, 'label' => $label];
            }
        }

        return $available;
    }

    /**
     * @param  array<string, int>  $path  optional area filter, e.g. ['district_id' => 3]
     * @return array{points: list<array<string, mixed>>, truncated: bool}
     */
    public function staffPoints(User $user, string $layer, array $path = []): array
    {
        abort_unless(in_array($layer, array_column($this->availableTo($user), 'key'), true), 403);

        $limit = (int) config('lccms.maps.max_points');

        $query = match ($layer) {
            'communities' => $this->geoScope->applyToLevel(Community::query(), GeoLevel::Community, $user)
                ->select(['id', 'name', 'reference_code', 'latitude', 'longitude', 'verification_status']),
            'facilities' => Facility::query()->visibleTo($user)
                ->where('status', '!=', RecordStatus::Archived)
                ->select(['id', 'name', 'reference_code', 'category', 'operating_status', 'latitude', 'longitude', 'verification_status']),
            'infrastructure' => InfrastructureAsset::query()->visibleTo($user)
                ->where('status', '!=', RecordStatus::Archived)
                ->select(['id', 'name', 'reference_code', 'category', 'condition', 'latitude', 'longitude', 'verification_status']),
            'houses' => House::query()->accessibleBy($user)
                ->select(['id', 'reference_code', 'building_type', 'latitude', 'longitude']),
        };

        $this->filterByPath($query, $path, $layer === 'communities');

        $records = $query->whereNotNull('latitude')->whereNotNull('longitude')->orderBy('id')->limit($limit + 1)->get();

        $points = $records->take($limit)->map(fn ($record): array => match ($layer) {
            'communities' => $this->point($record, $record->name, $record->reference_code, $record->verification_status?->label(), null),
            'facilities' => $this->point($record, $record->name, $record->category?->label(), $record->operating_status?->label(), route('admin.facilities.show', $record)),
            'infrastructure' => $this->point($record, $record->name, $record->category?->label(), $record->condition?->label(), route('admin.infrastructure.edit', $record)),
            'houses' => $this->point($record, $record->reference_code, $record->building_type?->label(), null, null),
        })->values()->all();

        return ['points' => $points, 'truncated' => $records->count() > $limit];
    }

    /**
     * @return array{communities: list<array<string, mixed>>, facilities: list<array<string, mixed>>}
     */
    public function publicPoints(): array
    {
        $limit = (int) config('lccms.maps.max_points');

        $communities = Community::query()
            ->where('is_published', true)
            ->where('status', RecordStatus::Active)
            ->where('verification_status', VerificationStatus::Verified)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'slug', 'latitude', 'longitude'])
            ->map(fn (Community $community): array => $this->point($community, $community->name, null, null, route('communities.show', $community)))
            ->all();

        $facilities = Facility::query()
            ->where('is_public', true)
            ->where('status', RecordStatus::Active)
            ->where('verification_status', VerificationStatus::Verified)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->limit($limit)
            ->get(['id', 'name', 'category', 'operating_status', 'latitude', 'longitude'])
            ->map(fn (Facility $facility): array => $this->point($facility, $facility->name, $facility->category?->label(), $facility->operating_status?->label(), null))
            ->all();

        return ['communities' => $communities, 'facilities' => $facilities];
    }

    /**
     * @param  Builder<Model>  $query
     * @param  array<string, int>  $path
     */
    private function filterByPath(Builder $query, array $path, bool $isCommunityTable): void
    {
        foreach ($path as $column => $id) {
            // The communities table identifies itself by id, not community_id.
            if ($isCommunityTable && in_array($column, ['community_id', 'block_id'], true)) {
                if ($column === 'community_id') {
                    $query->whereKey($id);
                }

                continue;
            }

            $query->where($query->getModel()->qualifyColumn($column), $id);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function point(object $record, ?string $label, ?string $kind, ?string $status, ?string $url): array
    {
        return [
            'id' => $record->id,
            'lat' => (float) $record->latitude,
            'lng' => (float) $record->longitude,
            'label' => $label,
            'kind' => $kind,
            'status' => $status,
            'url' => $url,
        ];
    }
}
