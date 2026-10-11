<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Sync\ConflictStatus;
use App\Enums\Sync\SyncOperationStatus;
use App\Models\Census\CampaignAssignment;
use App\Models\Sync\SyncConflict;
use App\Models\Sync\SyncOperation;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Geography\GeoScope;
use App\Services\Reports\ReportDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Offline synchronisation health per field worker: how many operations were
 * applied, deduplicated, rejected or held as conflicts, and when they last synced.
 *
 * Operations carry no geography of their own, so non-national viewers see
 * the field workers assigned to areas within their reach.
 */
class SyncOutcomesReport extends ReportDefinition
{
    public function key(): string
    {
        return 'sync-outcomes';
    }

    public function title(): string
    {
        return 'Sync outcomes and conflicts';
    }

    public function description(): string
    {
        return 'Offline operations received per field worker by outcome, open conflicts, and the most recent sync.';
    }

    public function category(): string
    {
        return 'Field operations';
    }

    public function authorize(User $user): bool
    {
        return $user->can(Permission::ReportsView->value)
            && ($user->can(Permission::SyncResolveConflicts->value) || $user->can(Permission::DevicesManage->value));
    }

    public function filters(): array
    {
        return ['campaign' => [], 'period' => []];
    }

    public function columns(array $filters): array
    {
        return [
            self::text('field_worker', 'Field worker'),
            self::number('operations', 'Operations'),
            ...array_map(
                fn (SyncOperationStatus $status): array => self::number($status->value, $status->label()),
                [SyncOperationStatus::Applied, SyncOperationStatus::Duplicate, SyncOperationStatus::Rejected, SyncOperationStatus::Conflict, SyncOperationStatus::Retryable],
            ),
            self::number('open_conflicts', 'Open conflicts'),
            self::text('last_sync', 'Last sync'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        if ($filters['campaign'] === null) {
            return [];
        }

        $campaignId = $filters['campaign']->id;
        $fieldWorkers = null;

        if (! app(GeoScope::class)->for($user)->national) {
            $fieldWorkers = app(CensusAccess::class)
                ->applyToRecords(CampaignAssignment::query(), $user)
                ->where('campaign_id', $campaignId)
                ->distinct()
                ->pluck('user_id');
        }

        $operations = $this->wherePeriod(SyncOperation::query(), 'created_at', $filters['from'], $filters['to'])
            ->where('campaign_id', $campaignId)
            ->when($fieldWorkers !== null, fn ($query) => $query->whereIn('user_id', $fieldWorkers))
            ->toBase()
            ->select('user_id', DB::raw('COUNT(*) AS operations'), DB::raw('MAX(processed_at) AS last_sync'))
            ->selectRaw("SUM(status = 'applied') AS applied")
            ->selectRaw("SUM(status = 'duplicate') AS duplicate")
            ->selectRaw("SUM(status = 'rejected') AS rejected")
            ->selectRaw("SUM(status = 'conflict') AS conflict")
            ->selectRaw("SUM(status = 'retryable') AS retryable")
            ->groupBy('user_id')
            ->get()
            ->keyBy('user_id');

        $openConflicts = SyncConflict::query()
            ->visibleTo($user)
            ->where('sync_conflicts.campaign_id', $campaignId)
            ->where('sync_conflicts.status', ConflictStatus::Open)
            ->toBase()
            ->select('raised_by', DB::raw('COUNT(*) AS total'))
            ->groupBy('raised_by')
            ->pluck('total', 'raised_by');

        $names = User::query()->whereIn('id', $operations->keys())->pluck('name', 'id');

        return $operations
            ->map(fn ($row, $id): array => [
                'field_worker' => $names[$id] ?? "User #{$id}",
                'operations' => (int) $row->operations,
                'applied' => (int) $row->applied,
                'duplicate' => (int) $row->duplicate,
                'rejected' => (int) $row->rejected,
                'conflict' => (int) $row->conflict,
                'retryable' => (int) $row->retryable,
                'open_conflicts' => (int) ($openConflicts[$id] ?? 0),
                'last_sync' => $row->last_sync,
            ])
            ->sortByDesc('operations')
            ->values()
            ->all();
    }
}
