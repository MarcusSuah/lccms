<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Models\Census\HouseEnumeration;
use App\Models\Census\Household;
use App\Models\Census\Occupant;
use App\Models\User;
use App\Services\Reports\ReportDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Output and quality per enumerator for one campaign: visits, households,
 * people, and how much of it passed or failed review.
 */
class EnumeratorPerformanceReport extends ReportDefinition
{
    public function key(): string
    {
        return 'enumerator-performance';
    }

    public function title(): string
    {
        return 'Enumerator performance';
    }

    public function description(): string
    {
        return 'Houses visited, households and occupants recorded per enumerator, with review outcomes and the latest visit.';
    }

    public function category(): string
    {
        return 'Field operations';
    }

    public function authorize(User $user): bool
    {
        return $user->can(Permission::CensusView->value)
            && ($user->can(Permission::ReportsView->value) || $user->can(Permission::AssignmentsManage->value));
    }

    public function filters(): array
    {
        return ['campaign' => [], 'area' => []];
    }

    public function columns(array $filters): array
    {
        return [
            self::text('enumerator', 'Enumerator'),
            self::number('houses_visited', 'Houses visited'),
            self::number('households', 'Households'),
            self::number('households_submitted', 'Submitted'),
            self::number('households_approved', 'Approved'),
            self::number('households_rejected', 'Rejected'),
            self::number('approval_rate', 'Approval rate (%)'),
            self::number('occupants', 'Occupants'),
            self::text('last_visit', 'Last visit'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        if ($filters['campaign'] === null) {
            return [];
        }

        $campaignId = $filters['campaign']->id;

        $visits = $this->wherePath(HouseEnumeration::query()->accessibleBy($user), $filters['area_path'])
            ->where('campaign_id', $campaignId)
            ->where('status', '!=', 'archived')
            ->toBase()
            ->select('enumerator_id', DB::raw('COUNT(*) AS visited'), DB::raw('MAX(visited_at) AS last_visit'))
            ->groupBy('enumerator_id')
            ->get()
            ->keyBy('enumerator_id');

        $households = $this->wherePath(Household::query()->accessibleBy($user), $filters['area_path'])
            ->where('campaign_id', $campaignId)
            ->where('status', '!=', 'archived')
            ->toBase()
            ->select('enumerator_id', DB::raw('COUNT(*) AS total'))
            ->selectRaw("SUM(status IN ('submitted', 'verified', 'approved', 'rejected')) AS submitted")
            ->selectRaw("SUM(status = 'approved') AS approved")
            ->selectRaw("SUM(status = 'rejected') AS rejected")
            ->groupBy('enumerator_id')
            ->get()
            ->keyBy('enumerator_id');

        $occupants = $this->wherePath(Occupant::query()->accessibleBy($user), $filters['area_path'])
            ->where('campaign_id', $campaignId)
            ->where('status', '!=', 'archived')
            ->toBase()
            ->select('enumerator_id', DB::raw('COUNT(*) AS total'))
            ->groupBy('enumerator_id')
            ->pluck('total', 'enumerator_id');

        $enumeratorIds = $visits->keys()->merge($households->keys())->merge($occupants->keys())->filter()->unique();
        $names = User::query()->whereIn('id', $enumeratorIds)->pluck('name', 'id');

        return $enumeratorIds
            ->map(function ($id) use ($visits, $households, $occupants, $names): array {
                $household = $households->get($id);
                $submitted = (int) ($household->submitted ?? 0);
                $approved = (int) ($household->approved ?? 0);

                return [
                    'enumerator' => $names[$id] ?? "User #{$id}",
                    'houses_visited' => (int) ($visits->get($id)->visited ?? 0),
                    'households' => (int) ($household->total ?? 0),
                    'households_submitted' => $submitted,
                    'households_approved' => $approved,
                    'households_rejected' => (int) ($household->rejected ?? 0),
                    'approval_rate' => $submitted > 0 ? round($approved / $submitted * 100, 1) : null,
                    'occupants' => (int) ($occupants[$id] ?? 0),
                    'last_visit' => $visits->get($id)->last_visit ?? null,
                ];
            })
            ->sortBy('enumerator', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
