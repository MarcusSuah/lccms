<?php

namespace App\Services\Facilities;

use App\Enums\Facilities\OperatingStatus;
use App\Enums\Facilities\ServiceAssessmentStatus;
use App\Enums\Facilities\ServiceCategory;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Facilities\CommunityServiceAssessment;
use App\Models\Facilities\Facility;
use App\Models\Geography\Community;
use App\Models\Infrastructure\InfrastructureAsset;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Service availability per community, combining the facility register, the
 * infrastructure register and explicit assessments. The resulting status for
 * each service is one of:
 *
 *  - present: a verified, working facility or asset exists, or an assessment says so
 *  - gap: facilities or assets are recorded but none is working
 *  - verified_absent: an assessor confirmed the service does not exist
 *  - unverified: something is recorded but not yet verified
 *  - not_assessed: nothing is known — never reported as "none"
 */
class NeedsAssessment
{
    /**
     * @var array<string, list<string>>
     */
    private const FACILITY_SOURCES = [
        'school' => ['school'],
        'health_facility' => ['hospital', 'clinic', 'other_health'],
        'market' => ['market'],
        'public_toilet' => ['sanitation'],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const INFRASTRUCTURE_SOURCES = [
        'water' => ['water'],
        'electricity' => ['electricity'],
        'internet' => ['internet'],
        'waste_collection' => ['waste_management'],
        'road_access' => ['road', 'bridge'],
    ];

    /**
     * @param  array{search?: string|null, gaps_only?: bool}  $filters
     */
    public function report(User $user, array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $communities = Community::query()
            ->visibleTo($user)
            ->with(['town:id,name', 'district:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->search($search))
            ->when($filters['gaps_only'] ?? false, fn (Builder $query) => $this->onlyWithGaps($query))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();

        $ids = $communities->getCollection()->pluck('id')->all();
        $matrix = $this->matrix($ids);

        return $communities->through(fn (Community $community): array => [
            'id' => $community->id,
            'name' => $community->name,
            'reference_code' => $community->reference_code,
            'location' => trim(($community->town?->name ?? '').' · '.($community->district?->name ?? ''), ' ·'),
            'services' => $matrix[$community->id] ?? $this->emptyRow(),
        ]);
    }

    /**
     * @param  list<int>  $communityIds
     * @return array<int, array<string, string>>
     */
    public function matrix(array $communityIds): array
    {
        if ($communityIds === []) {
            return [];
        }

        $facilities = Facility::query()
            ->whereIn('community_id', $communityIds)
            ->where('status', '!=', RecordStatus::Archived)
            ->toBase()
            ->select('community_id', 'category')
            ->selectRaw('COUNT(*) AS recorded')
            ->selectRaw('SUM(verification_status = ?) AS verified', [VerificationStatus::Verified->value])
            ->selectRaw('SUM(verification_status = ? AND operating_status IN (?, ?)) AS working', [
                VerificationStatus::Verified->value, OperatingStatus::Operational->value, OperatingStatus::PartiallyOperational->value,
            ])
            ->groupBy('community_id', 'category')
            ->get()
            ->groupBy('community_id');

        $infrastructure = InfrastructureAsset::query()
            ->whereIn('community_id', $communityIds)
            ->where('status', '!=', RecordStatus::Archived)
            ->toBase()
            ->select('community_id', 'category')
            ->selectRaw('COUNT(*) AS recorded')
            ->selectRaw('SUM(verification_status = ?) AS verified', [VerificationStatus::Verified->value])
            ->selectRaw("SUM(verification_status = ? AND `condition` IN ('good', 'fair') AND availability <> 'unavailable') AS working", [VerificationStatus::Verified->value])
            ->groupBy('community_id', 'category')
            ->get()
            ->groupBy('community_id');

        $assessments = CommunityServiceAssessment::query()
            ->whereIn('community_id', $communityIds)
            ->get(['community_id', 'category', 'status'])
            ->groupBy('community_id');

        $matrix = [];

        foreach ($communityIds as $id) {
            $row = [];

            foreach (ServiceCategory::cases() as $service) {
                $records = $this->sourceRows($service, $facilities->get($id), $infrastructure->get($id));
                $assessment = $assessments->get($id)?->firstWhere('category', $service)?->status;

                $row[$service->value] = $this->status($records, $assessment);
            }

            $matrix[$id] = $row;
        }

        return $matrix;
    }

    /**
     * @param  Collection<int, object>|null  $facilities
     * @param  Collection<int, object>|null  $infrastructure
     * @return array{recorded: int, verified: int, working: int}
     */
    private function sourceRows(ServiceCategory $service, ?Collection $facilities, ?Collection $infrastructure): array
    {
        $rows = collect();

        if (isset(self::FACILITY_SOURCES[$service->value])) {
            $rows = ($facilities ?? collect())->whereIn('category', self::FACILITY_SOURCES[$service->value]);
        } elseif (isset(self::INFRASTRUCTURE_SOURCES[$service->value])) {
            $rows = ($infrastructure ?? collect())->whereIn('category', self::INFRASTRUCTURE_SOURCES[$service->value]);
        }

        return [
            'recorded' => (int) $rows->sum('recorded'),
            'verified' => (int) $rows->sum('verified'),
            'working' => (int) $rows->sum('working'),
        ];
    }

    /**
     * @param  array{recorded: int, verified: int, working: int}  $records
     */
    private function status(array $records, ?ServiceAssessmentStatus $assessment): string
    {
        if ($records['working'] > 0 || $assessment === ServiceAssessmentStatus::Present) {
            return 'present';
        }

        if ($records['verified'] > 0) {
            return 'gap';
        }

        if ($assessment === ServiceAssessmentStatus::VerifiedAbsent) {
            return 'verified_absent';
        }

        if ($records['recorded'] > 0) {
            return 'unverified';
        }

        return 'not_assessed';
    }

    /**
     * @return array<string, string>
     */
    private function emptyRow(): array
    {
        return collect(ServiceCategory::cases())->mapWithKeys(fn (ServiceCategory $service): array => [$service->value => 'not_assessed'])->all();
    }

    /**
     * Communities with a verified absence, a closed verified facility or a
     * verified failing infrastructure asset.
     *
     * @param  Builder<Community>  $query
     */
    private function onlyWithGaps(Builder $query): void
    {
        $query->where(function (Builder $inner): void {
            $inner->whereExists(fn ($sub) => $sub->selectRaw('1')->from('community_service_assessments')
                ->whereColumn('community_service_assessments.community_id', 'communities.id')
                ->where('status', ServiceAssessmentStatus::VerifiedAbsent->value))
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('facilities')
                    ->whereColumn('facilities.community_id', 'communities.id')
                    ->where('verification_status', VerificationStatus::Verified->value)
                    ->where('operating_status', OperatingStatus::Closed->value))
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')->from('infrastructure_assets')
                    ->whereColumn('infrastructure_assets.community_id', 'communities.id')
                    ->where('verification_status', VerificationStatus::Verified->value)
                    ->where(fn ($failing) => $failing->whereIn('condition', ['poor', 'not_functional'])->orWhere('availability', 'unavailable')));
        });
    }

    /**
     * Facility counts by category and ownership for an area, one count per facility.
     *
     * @param  array<string, int>  $area  path filter
     * @return array<string, array<string, int>>
     */
    public function facilityCounts(?User $user, array $area = [], bool $publicOnly = false): array
    {
        return Facility::query()
            ->when($user !== null, fn (Builder $query) => $query->visibleTo($user))
            ->when($publicOnly, fn (Builder $query) => $query->where('is_public', true)->where('verification_status', VerificationStatus::Verified))
            ->where('status', '!=', RecordStatus::Archived)
            ->where(function (Builder $query) use ($area): void {
                foreach ($area as $column => $id) {
                    $query->where($column, $id);
                }
            })
            ->toBase()
            ->select('category', 'ownership')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('category', 'ownership')
            ->get()
            ->groupBy('category')
            ->map(fn (Collection $rows): array => $rows->pluck('total', 'ownership')->map(fn ($total): int => (int) $total)->all())
            ->all();
    }
}
