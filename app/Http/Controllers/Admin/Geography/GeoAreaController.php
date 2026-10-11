<?php

namespace App\Http\Controllers\Admin\Geography;

use App\Actions\Geography\SaveGeoArea;
use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Geography\SaveGeoAreaRequest;
use App\Http\Resources\Geography\GeoAreaResource;
use App\Models\Content\MediaItem;
use App\Models\Geography\Community;
use App\Models\Geography\GeoArea;
use App\Models\Geography\GeoReassignment;
use App\Services\Geography\GeoAreaResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GeoAreaController extends Controller
{
    public function index(Request $request, GeoLevel $level, GeoAreaResolver $resolver): Response
    {
        Gate::authorize('viewAny', [GeoArea::class]);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(RecordStatus::class)],
            'verification_status' => ['nullable', Rule::enum(VerificationStatus::class)],
        ]);

        $parent = isset($filters['parent_id']) && $level->parent() !== null
            ? $resolver->find($level->parent(), (int) $filters['parent_id'])?->load($level->parent()->modelClass()::ancestorRelations())
            : null;

        $class = $level->modelClass();

        $areas = $class::query()
            ->with($class::ancestorRelations())
            ->when($level->child() !== null, fn (Builder $query) => $query->withCount('children'))
            ->when($parent !== null, fn (Builder $query) => $query->where($level->parent()->column(), $parent->id))
            ->search($filters['search'] ?? null)
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/geography/index', [
            'level' => $level->value,
            'levels' => GeoLevel::options(),
            'areas' => GeoAreaResource::collection($areas),
            'parent' => $parent === null ? null : new GeoAreaResource($parent),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'parent_id' => $parent?->id,
                'status' => $filters['status'] ?? '',
                'verification_status' => $filters['verification_status'] ?? '',
            ],
            'statusOptions' => RecordStatus::options(),
            'verificationOptions' => VerificationStatus::options(),
            'can' => [
                'create' => $request->user()->can(Permission::GeographyManage->value),
                'import' => $request->user()->can(Permission::GeographyImport->value),
            ],
        ]);
    }

    public function show(Request $request, GeoLevel $level, GeoArea $geoArea): Response
    {
        Gate::authorize('view', $geoArea);

        $geoArea->load($geoArea::ancestorRelations());

        if ($level->child() !== null) {
            $geoArea->loadCount('children');
        }

        $children = $level->child() === null ? null : $geoArea->children()
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25, ['id', 'name', 'reference_code', 'status', 'verification_status', 'latitude', 'longitude'])
            ->through(fn (GeoArea $child): array => [
                'id' => $child->id,
                'name' => $child->name,
                'reference_code' => $child->reference_code,
                'status' => $child->status->value,
                'verification_status' => $child->verification_status->value,
            ]);

        $reassignments = GeoReassignment::query()
            ->where('level', $level->value)
            ->where('area_id', $geoArea->id)
            ->with(['requester:id,name', 'decider:id,name'])
            ->latest('id')
            ->limit(20)
            ->get();

        return Inertia::render('admin/geography/show', [
            'level' => $level->value,
            'area' => new GeoAreaResource($geoArea),
            'childLevel' => $level->child()?->value,
            'childAreas' => $children,
            'reassignments' => $reassignments->map(fn (GeoReassignment $reassignment): array => $reassignment->toSummary()),
            'activity' => $geoArea->activitiesAsSubject()
                ->with('causer:id,name')
                ->latest('id')
                ->limit(15)
                ->get()
                ->map(fn ($activity): array => [
                    'id' => $activity->id,
                    'event' => $activity->event,
                    'by' => $activity->causer?->name,
                    'at' => $activity->created_at?->toIso8601String(),
                    'changes' => array_keys($activity->attribute_changes?->get('attributes') ?? []),
                ]),
            'media' => $geoArea instanceof Community
                ? $geoArea->media()->orderByDesc('is_featured')->orderBy('sort_order')->get()->map(fn (MediaItem $item): array => $item->toManagerArray())
                : null,
            'can' => [
                'manageMedia' => $geoArea instanceof Community && $request->user()->can(Permission::ContentManage->value),
                'update' => $request->user()->can('update', $geoArea),
                'reassign' => $request->user()->can('requestReassignment', $geoArea),
                'createChild' => $level->child() !== null && $request->user()->can('createUnder', [GeoArea::class, $level->child(), $geoArea]),
            ],
        ]);
    }

    public function create(Request $request, GeoLevel $level, GeoAreaResolver $resolver): Response
    {
        $parent = $level->parent() !== null && $request->integer('parent_id') > 0
            ? $resolver->find($level->parent(), $request->integer('parent_id'))?->load($level->parent()->modelClass()::ancestorRelations())
            : null;

        abort_unless($request->user()->can(Permission::GeographyManage->value), 403);

        if ($level === GeoLevel::County) {
            Gate::authorize('createUnder', [GeoArea::class, $level, null]);
        }

        return Inertia::render('admin/geography/form', [
            'level' => $level->value,
            'area' => null,
            'parent' => $parent === null ? null : new GeoAreaResource($parent),
            ...$this->formOptions(),
        ]);
    }

    public function store(SaveGeoAreaRequest $request, GeoLevel $level, GeoAreaResolver $resolver, SaveGeoArea $save): RedirectResponse
    {
        $parent = $level->parent() === null ? null : $resolver->find($level->parent(), $request->integer('parent_id'));

        Gate::authorize('createUnder', [GeoArea::class, $level, $parent]);

        $area = $save->handle($level, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':level ":name" created with reference :code.', [
            'level' => $level->label(), 'name' => $area->name, 'code' => $area->reference_code,
        ])]);

        return to_route('admin.geography.show', ['level' => $level, 'geoArea' => $area->id]);
    }

    public function edit(GeoLevel $level, GeoArea $geoArea): Response
    {
        Gate::authorize('update', $geoArea);

        $geoArea->load($geoArea::ancestorRelations());

        return Inertia::render('admin/geography/form', [
            'level' => $level->value,
            'area' => new GeoAreaResource($geoArea),
            'parent' => null,
            ...$this->formOptions(),
        ]);
    }

    public function update(SaveGeoAreaRequest $request, GeoLevel $level, GeoArea $geoArea, SaveGeoArea $save): RedirectResponse
    {
        Gate::authorize('update', $geoArea);

        $save->handle($level, $request->validated(), $geoArea);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Changes saved.')]);

        return to_route('admin.geography.show', ['level' => $level, 'geoArea' => $geoArea->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(): array
    {
        return [
            'statusOptions' => RecordStatus::options(),
            'verificationOptions' => VerificationStatus::options(),
            'levels' => GeoLevel::options(),
        ];
    }
}
