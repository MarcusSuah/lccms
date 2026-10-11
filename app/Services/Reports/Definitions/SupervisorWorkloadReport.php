<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Census\AssignmentRole;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\Household;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Reports\ReportDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Review queue per supervisor: what is waiting for verification, how long
 * the oldest item has waited, and how many enumerators report to them.
 */
class SupervisorWorkloadReport extends ReportDefinition
{
    public function key(): string
    {
        return 'supervisor-workload';
    }

    public function title(): string
    {
        return 'Supervisor workload';
    }

    public function description(): string
    {
        return 'Households awaiting verification or approval per supervisor, the age of the oldest waiting item, and team size.';
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
            self::text('supervisor', 'Supervisor'),
            self::number('enumerators', 'Enumerators'),
            self::number('awaiting_verification', 'Awaiting verification'),
            self::number('awaiting_approval', 'Awaiting approval'),
            self::number('oldest_waiting_days', 'Oldest waiting (days)'),
            self::number('verified', 'Verified or approved'),
            self::number('rejected', 'Rejected'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        if ($filters['campaign'] === null) {
            return [];
        }

        $campaignId = $filters['campaign']->id;

        $households = $this->wherePath(Household::query()->accessibleBy($user), $filters['area_path'])
            ->where('campaign_id', $campaignId)
            ->whereNotNull('supervisor_id')
            ->toBase()
            ->select('supervisor_id')
            ->selectRaw("SUM(status = 'submitted') AS awaiting_verification")
            ->selectRaw("SUM(status = 'verified') AS awaiting_approval")
            ->selectRaw("SUM(status IN ('verified', 'approved')) AS verified")
            ->selectRaw("SUM(status = 'rejected') AS rejected")
            ->selectRaw("MIN(CASE WHEN status IN ('submitted', 'verified') THEN submitted_at END) AS oldest_waiting")
            ->groupBy('supervisor_id')
            ->get()
            ->keyBy('supervisor_id');

        $teams = $this->wherePath(app(CensusAccess::class)->applyToRecords(CampaignAssignment::query(), $user), $filters['area_path'])
            ->current()
            ->where('campaign_id', $campaignId)
            ->where('role', AssignmentRole::Enumerator)
            ->whereNotNull('supervisor_id')
            ->toBase()
            ->select('supervisor_id', DB::raw('COUNT(DISTINCT user_id) AS enumerators'))
            ->groupBy('supervisor_id')
            ->pluck('enumerators', 'supervisor_id');

        $supervisorIds = $households->keys()->merge($teams->keys())->unique();
        $names = User::query()->whereIn('id', $supervisorIds)->pluck('name', 'id');

        return $supervisorIds
            ->map(function ($id) use ($households, $teams, $names): array {
                $row = $households->get($id);

                return [
                    'supervisor' => $names[$id] ?? "User #{$id}",
                    'enumerators' => (int) ($teams[$id] ?? 0),
                    'awaiting_verification' => (int) ($row->awaiting_verification ?? 0),
                    'awaiting_approval' => (int) ($row->awaiting_approval ?? 0),
                    'oldest_waiting_days' => isset($row->oldest_waiting) ? (int) now()->parse($row->oldest_waiting)->diffInDays(now()) : null,
                    'verified' => (int) ($row->verified ?? 0),
                    'rejected' => (int) ($row->rejected ?? 0),
                ];
            })
            ->sortByDesc('awaiting_verification')
            ->values()
            ->all();
    }
}
