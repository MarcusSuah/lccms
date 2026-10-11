<?php

namespace App\Services\Reports;

use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Models\Census\CensusCampaign;
use App\Models\Geography\GeoArea;
use App\Models\User;
use App\Services\Census\CensusAccess;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * A report shown on screen and exported to CSV, Excel or PDF from the same
 * rows, so the two can never disagree. Every definition filters by the
 * viewer's permissions and geographic reach; nothing here trusts the client
 * beyond the validated filter values.
 *
 * Filters are chosen from a fixed vocabulary so the generic report page can
 * render them: campaign, area, breakdown (a level to group by), period and
 * status (with options).
 */
abstract class ReportDefinition
{
    abstract public function key(): string;

    abstract public function title(): string;

    abstract public function description(): string;

    /**
     * Hub section the report is listed under.
     */
    abstract public function category(): string;

    abstract public function authorize(User $user): bool;

    /**
     * @param  array<string, mixed>  $filters  resolved filters
     * @return list<array{key: string, label: string, numeric: bool}>
     */
    abstract public function columns(array $filters): array;

    /**
     * @param  array<string, mixed>  $filters  resolved filters
     * @return iterable<array<string, scalar|null>>
     */
    abstract public function rows(User $user, array $filters): iterable;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function filters(): array
    {
        return [];
    }

    /**
     * Whether the report lists identifiable people (not public office holders).
     */
    public function containsPersonalData(): bool
    {
        return false;
    }

    public function canExport(User $user): bool
    {
        return $this->authorize($user)
            && $user->can(Permission::ReportsExport->value)
            && (! $this->containsPersonalData() || $user->can(Permission::ReportsExportPersonalData->value));
    }

    /**
     * Validate raw filter input and turn it into resolved values: a campaign
     * model, an area path, a breakdown level and dates.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function resolveFilters(User $user, array $input): array
    {
        $filters = $this->filters();
        $rules = [];

        if (isset($filters['campaign'])) {
            $rules['campaign'] = ['nullable', 'integer'];
        }

        if (isset($filters['area'])) {
            $rules['level'] = ['nullable', Rule::enum(GeoLevel::class)];
            $rules['area_id'] = ['nullable', 'required_with:level', 'integer'];
        }

        if (isset($filters['breakdown'])) {
            $rules['breakdown'] = ['nullable', Rule::enum(GeoLevel::class)];
        }

        if (isset($filters['period'])) {
            $rules['from'] = ['nullable', 'date'];
            $rules['to'] = ['nullable', 'date', 'after_or_equal:from'];
        }

        if (isset($filters['status'])) {
            $rules['status'] = ['nullable', Rule::in(array_column($filters['status']['options'], 'value'))];
        }

        $validated = Validator::make($input, $rules)->validate();
        $resolved = [];

        if (isset($filters['campaign'])) {
            $campaigns = $this->campaignsFor($user);
            $requested = (int) ($validated['campaign'] ?? 0);
            $campaign = $requested > 0 ? $campaigns->firstWhere('id', $requested) : $campaigns->first();

            if ($requested > 0 && $campaign === null) {
                throw ValidationException::withMessages(['campaign' => 'Choose a campaign you have access to.']);
            }

            $resolved['campaign'] = $campaign;
        }

        if (isset($filters['area'])) {
            $resolved['area'] = null;
            $resolved['area_path'] = [];

            if (isset($validated['level'])) {
                $level = GeoLevel::from($validated['level']);
                $area = app(GeoAreaResolver::class)->find($level, (int) $validated['area_id']);

                if ($area === null) {
                    throw ValidationException::withMessages(['area_id' => 'The selected area does not exist.']);
                }

                $resolved['area'] = $area;
                $resolved['area_path'] = [$level->column() => $area->id];
            }
        }

        if (isset($filters['breakdown'])) {
            $default = $resolved['area'] instanceof GeoArea
                ? ($resolved['area']::level()->child() ?? $resolved['area']::level())
                : GeoLevel::County;
            $resolved['breakdown'] = GeoLevel::tryFrom($validated['breakdown'] ?? '') ?? $default;
        }

        if (isset($filters['period'])) {
            $resolved['from'] = $validated['from'] ?? null;
            $resolved['to'] = $validated['to'] ?? null;
        }

        if (isset($filters['status'])) {
            $resolved['status'] = $validated['status'] ?? null;
        }

        return $resolved;
    }

    /**
     * Resolved filters reduced to plain values, for storing with an export
     * and echoing back to the page.
     *
     * @param  array<string, mixed>  $resolved
     * @return array<string, scalar|null>
     */
    public function serializeFilters(array $resolved): array
    {
        $plain = [];

        foreach ($resolved as $key => $value) {
            match (true) {
                $key === 'area_path' => null,
                $value instanceof CensusCampaign => $plain['campaign'] = $value->id,
                $key === 'area' && $value instanceof GeoArea => [$plain['level'], $plain['area_id']] = [$value::level()->value, $value->id],
                $value instanceof GeoLevel => $plain[$key] = $value->value,
                default => $plain[$key] = $value,
            };
        }

        return array_filter($plain, fn ($value): bool => $value !== null);
    }

    /**
     * Human readable summary of the filters, printed on PDF exports.
     *
     * @param  array<string, mixed>  $resolved
     * @return array<string, string>
     */
    public function describeFilters(array $resolved): array
    {
        $description = [];

        if (($resolved['campaign'] ?? null) instanceof CensusCampaign) {
            $description['Campaign'] = "{$resolved['campaign']->name} ({$resolved['campaign']->reference_code})";
        }

        if (($resolved['area'] ?? null) instanceof GeoArea) {
            $description['Area'] = collect($resolved['area']->breadcrumb())->pluck('name')->implode(' › ');
        } elseif (isset($this->filters()['area'])) {
            $description['Area'] = 'All areas in reach';
        }

        if (($resolved['breakdown'] ?? null) instanceof GeoLevel) {
            $description['Grouped by'] = $resolved['breakdown']->label();
        }

        if (($resolved['from'] ?? null) !== null || ($resolved['to'] ?? null) !== null) {
            $description['Period'] = ($resolved['from'] ?? 'start').' to '.($resolved['to'] ?? 'today');
        }

        if (($resolved['status'] ?? null) !== null) {
            $description['Status'] = str_replace('_', ' ', ucfirst((string) $resolved['status']));
        }

        return $description;
    }

    /**
     * Campaigns the user can see, newest first.
     *
     * @return Collection<int, CensusCampaign>
     */
    public function campaignsFor(User $user): Collection
    {
        $visible = app(CensusAccess::class)->visibleCampaignIds($user);

        return CensusCampaign::query()
            ->when($visible !== null, fn (Builder $query) => $query->whereIn('id', $visible))
            ->orderByDesc('reference_date')
            ->get(['id', 'name', 'reference_code', 'status', 'reference_date']);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, int>  $path
     * @return Builder<TModel>
     */
    protected function wherePath(Builder $query, array $path): Builder
    {
        foreach ($path as $column => $id) {
            $query->where($query->getModel()->qualifyColumn($column), $id);
        }

        return $query;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function wherePeriod(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from !== null, fn (Builder $query) => $query->where($query->getModel()->qualifyColumn($column), '>=', $from))
            ->when($to !== null, fn (Builder $query) => $query->where($query->getModel()->qualifyColumn($column), '<', now()->parse($to)->addDay()->toDateString()));
    }

    /**
     * Area names for a set of ids at one level, keyed by id.
     *
     * @param  iterable<int|string|null>  $ids
     * @return array<int, string>
     */
    protected function areaNames(GeoLevel $level, iterable $ids): array
    {
        $ids = collect($ids)->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $class = $level->modelClass();

        return $class::query()->whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    /**
     * @return array{key: string, label: string, numeric: bool}
     */
    protected static function text(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'numeric' => false];
    }

    /**
     * @return array{key: string, label: string, numeric: bool}
     */
    protected static function number(string $key, string $label): array
    {
        return ['key' => $key, 'label' => $label, 'numeric' => true];
    }
}
