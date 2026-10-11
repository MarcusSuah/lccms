<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Enums\VerificationStatus;
use App\Models\Leadership\LeadershipTenure;
use App\Models\User;
use App\Services\Reports\ReportDefinition;

/**
 * Current office holders per community and block. Contact numbers are never
 * included; the register is about who holds which office, not how to reach them.
 */
class LeadershipReport extends ReportDefinition
{
    public function key(): string
    {
        return 'leadership';
    }

    public function title(): string
    {
        return 'Current leadership';
    }

    public function description(): string
    {
        return 'Who currently holds each community and block office, since when, how they were selected and whether it is verified.';
    }

    public function category(): string
    {
        return 'Services and infrastructure';
    }

    public function authorize(User $user): bool
    {
        return $user->can(Permission::LeadershipView->value);
    }

    public function filters(): array
    {
        return [
            'area' => [],
            'status' => ['label' => 'Verification', 'options' => VerificationStatus::options()],
        ];
    }

    public function columns(array $filters): array
    {
        return [
            self::text('community', 'Community'),
            self::text('block', 'Block'),
            self::text('position', 'Position'),
            self::text('holder', 'Holder'),
            self::text('since', 'Since'),
            self::text('selection_method', 'Selection'),
            self::text('verification', 'Verification'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        $tenures = $this->wherePath(LeadershipTenure::query()->visibleTo($user), $filters['area_path'])
            ->current()
            ->when($filters['status'] !== null, fn ($query) => $query->where('verification_status', $filters['status']))
            ->with('position:id,title')
            ->orderBy('community_id')
            ->orderBy('block_id')
            ->limit(20000)
            ->get();

        $communities = $this->areaNames(GeoLevel::Community, $tenures->pluck('community_id'));
        $blocks = $this->areaNames(GeoLevel::Block, $tenures->pluck('block_id'));

        return $tenures
            ->map(fn (LeadershipTenure $tenure): array => [
                'community' => $communities[$tenure->community_id] ?? null,
                'block' => $blocks[$tenure->block_id] ?? null,
                'position' => $tenure->position?->title,
                'holder' => $tenure->holder_name,
                'since' => $tenure->starts_on?->toDateString(),
                'selection_method' => $tenure->selection_method?->label(),
                'verification' => $tenure->verification_status?->label(),
            ])
            ->sortBy([['community', 'asc'], ['block', 'asc'], ['position', 'asc']])
            ->values()
            ->all();
    }
}
