<?php

namespace App\Services\Reports\Definitions;

use App\Enums\Access\Permission;
use App\Enums\Facilities\FacilityCategory;
use App\Enums\Facilities\OperatingStatus;
use App\Enums\Facilities\Ownership;
use App\Enums\RecordStatus;
use App\Models\Facilities\Facility;
use App\Models\User;
use App\Services\Reports\ReportDefinition;
use Illuminate\Support\Facades\DB;

/**
 * Facilities per area by category, with operating status and ownership
 * totals. Archived facilities are excluded.
 */
class FacilitiesReport extends ReportDefinition
{
    public function key(): string
    {
        return 'facilities';
    }

    public function title(): string
    {
        return 'Facilities by area';
    }

    public function description(): string
    {
        return 'Schools, health facilities, markets and other services per area, with how many are operating and how many are publicly owned.';
    }

    public function category(): string
    {
        return 'Services and infrastructure';
    }

    public function authorize(User $user): bool
    {
        return $user->can(Permission::FacilitiesView->value);
    }

    public function filters(): array
    {
        return [
            'area' => [],
            'breakdown' => [],
            'status' => ['label' => 'Ownership', 'options' => Ownership::options()],
        ];
    }

    public function columns(array $filters): array
    {
        return [
            self::text('area', $filters['breakdown']->label()),
            ...array_map(fn (FacilityCategory $category): array => self::number($category->value, $category->label()), FacilityCategory::cases()),
            self::number('total', 'Total'),
            self::number('operational', 'Operational'),
            self::number('not_operating', 'Closed or partial'),
            self::number('verified', 'Verified'),
        ];
    }

    public function rows(User $user, array $filters): iterable
    {
        $level = $filters['breakdown'];
        $column = $level->column();

        $counts = $this->wherePath(Facility::query()->visibleTo($user), $filters['area_path'])
            ->where('status', '!=', RecordStatus::Archived)
            ->when($filters['status'] !== null, fn ($query) => $query->where('ownership', $filters['status']))
            ->whereNotNull($column)
            ->toBase()
            ->select($column, 'category', DB::raw('COUNT(*) AS total'))
            ->selectRaw('SUM(operating_status = ?) AS operational', [OperatingStatus::Operational->value])
            ->selectRaw('SUM(operating_status IN (?, ?)) AS not_operating', [OperatingStatus::Closed->value, OperatingStatus::PartiallyOperational->value])
            ->selectRaw("SUM(verification_status = 'verified') AS verified")
            ->groupBy($column, 'category')
            ->get()
            ->groupBy($column);

        $names = $this->areaNames($level, $counts->keys());

        return $counts
            ->map(function ($rows, $areaId) use ($names): array {
                $row = ['area' => $names[$areaId] ?? "#{$areaId}"];

                foreach (FacilityCategory::cases() as $category) {
                    $row[$category->value] = (int) $rows->where('category', $category->value)->sum('total');
                }

                return [
                    ...$row,
                    'total' => (int) $rows->sum('total'),
                    'operational' => (int) $rows->sum('operational'),
                    'not_operating' => (int) $rows->sum('not_operating'),
                    'verified' => (int) $rows->sum('verified'),
                ];
            })
            ->sortBy('area', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
