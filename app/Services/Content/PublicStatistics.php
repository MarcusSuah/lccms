<?php

namespace App\Services\Content;

use App\Enums\Census\CampaignStatus;
use App\Enums\Geography\GeoLevel;
use App\Enums\Infrastructure\AssetCondition;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Models\Census\CensusCampaign;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use App\Models\Infrastructure\InfrastructureAsset;
use App\Services\Census\CensusStatistics;
use App\Services\Facilities\NeedsAssessment;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Figures for the public website. Population numbers come only from the most
 * recent closed campaign and only from approved records; facilities and
 * infrastructure only from verified records marked public. Nothing here can
 * reveal an individual, a household or a house location.
 */
class PublicStatistics
{
    public function __construct(
        private CensusStatistics $census,
        private NeedsAssessment $needs,
        private GeoAreaResolver $resolver,
    ) {}

    public function officialCampaign(): ?CensusCampaign
    {
        return CensusCampaign::query()
            ->whereIn('status', [CampaignStatus::Closed, CampaignStatus::Archived])
            ->orderByDesc('reference_date')
            ->orderByDesc('id')
            ->with('scopes')
            ->first();
    }

    /**
     * @param  array<string, int>  $area
     * @return array<string, mixed>|null null when no official result covers the area
     */
    public function population(array $area = []): ?array
    {
        $campaign = $this->officialCampaign();

        if ($campaign === null) {
            return null;
        }

        if ($area !== [] && ! $campaign->covers($this->fullPath($area))) {
            return null;
        }

        return [
            ...$this->census->publicSummary($campaign, $area),
            'campaign' => [
                'name' => $campaign->name,
                'reference_code' => $campaign->reference_code,
                'reference_date' => $campaign->reference_date->toDateString(),
            ],
        ];
    }

    /**
     * Expands a single-level filter such as ['community_id' => 5] to the area's
     * full ancestor path so campaign coverage at any level can be checked.
     *
     * @param  array<string, int>  $area
     * @return array<string, int|null>
     */
    private function fullPath(array $area): array
    {
        $column = array_key_last($area);
        $level = GeoLevel::tryFrom(str_replace('_id', '', (string) $column));
        $model = $level ? $this->resolver->find($level, (int) $area[$column]) : null;

        return $model === null ? $area : $this->resolver->fullPath($model);
    }

    /**
     * @param  array<string, int>  $area
     * @return array<string, array<string, int>>
     */
    public function facilities(array $area = []): array
    {
        return Cache::remember('public-facilities:'.md5(serialize($area)), now()->addMinutes(10), fn (): array => $this->needs->facilityCounts(null, $area, publicOnly: true));
    }

    /**
     * @param  array<string, int>  $area
     * @return list<array{category: string, total: int, needs_attention: int}>
     */
    public function infrastructure(array $area = []): array
    {
        return InfrastructureAsset::query()
            ->where('is_public', true)
            ->where('verification_status', VerificationStatus::Verified)
            ->where('status', '!=', RecordStatus::Archived)
            ->where(function ($query) use ($area): void {
                foreach ($area as $column => $id) {
                    $query->where($column, $id);
                }
            })
            ->toBase()
            ->select('category')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(`condition` IN (?, ?)) AS needs_attention', [AssetCondition::Poor->value, AssetCondition::NonFunctional->value])
            ->groupBy('category')
            ->get()
            ->map(fn ($row): array => ['category' => $row->category, 'total' => (int) $row->total, 'needs_attention' => (int) $row->needs_attention])
            ->all();
    }

    /**
     * @return array{communities: int, blocks: int}
     */
    public function registry(): array
    {
        return Cache::remember('public-registry-counts', now()->addMinutes(10), fn (): array => [
            'communities' => Community::query()->active()->where('is_published', true)->count(),
            'blocks' => Block::query()->active()->count(),
        ]);
    }
}
