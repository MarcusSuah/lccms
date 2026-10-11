<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Complaints\ComplaintCategory;
use App\Enums\Complaints\ComplaintStatus;
use App\Models\Complaints\Complaint;
use App\Models\User;
use App\Services\Reports\ReportDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Complaint volumes and handling times by category. Contains no complainant
 * details: only counts and durations.
 */
class ComplaintsReport extends ReportDefinition
{
    public function key(): string
    {
        return 'complaints';
    }

    public function title(): string
    {
        return 'Complaint statistics';
    }

    public function description(): string
    {
        return 'Complaints received by category, how many are open, resolved or rejected, and the average days to resolve.';
    }

    public function category(): string
    {
        return 'Public services';
    }

    public function authorize(User $user): bool
    {
        return $user->can(Permission::ComplaintsView->value);
    }

    public function filters(): array
    {
        return ['area' => [], 'period' => []];
    }

    public function columns(array $filters): array
    {
        return [
            self::text('category', 'Category'),
            self::number('received', 'Received'),
            self::number('open', 'Open'),
            self::number('resolved', 'Resolved or closed'),
            self::number('rejected', 'Rejected'),
            self::number('urgent', 'High or urgent'),
            self::number('average_days_to_resolve', 'Average days to resolve'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        $closed = [ComplaintStatus::Resolved->value, ComplaintStatus::Closed->value, ComplaintStatus::Rejected->value];

        $counts = $this->wherePeriod($this->wherePath(Complaint::query()->visibleTo($user), $filters['area_path']), 'created_at', $filters['from'], $filters['to'])
            ->toBase()
            ->select('category', DB::raw('COUNT(*) AS received'))
            ->selectRaw('SUM(status NOT IN (?, ?, ?)) AS open', $closed)
            ->selectRaw('SUM(status IN (?, ?)) AS resolved', [ComplaintStatus::Resolved->value, ComplaintStatus::Closed->value])
            ->selectRaw('SUM(status = ?) AS rejected', [ComplaintStatus::Rejected->value])
            ->selectRaw("SUM(priority IN ('high', 'urgent')) AS urgent")
            ->selectRaw('AVG(CASE WHEN resolved_at IS NOT NULL THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) AS resolve_hours')
            ->groupBy('category')
            ->get()
            ->keyBy('category');

        $rows = [];

        foreach (ComplaintCategory::cases() as $category) {
            $row = $counts->get($category->value);

            if ($row === null) {
                continue;
            }

            $rows[] = [
                'category' => $category->label(),
                'received' => (int) $row->received,
                'open' => (int) $row->open,
                'resolved' => (int) $row->resolved,
                'rejected' => (int) $row->rejected,
                'urgent' => (int) $row->urgent,
                'average_days_to_resolve' => $row->resolve_hours === null ? null : round($row->resolve_hours / 24, 1),
            ];
        }

        return $rows;
    }
}
