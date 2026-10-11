<?php

namespace App\Http\Controllers\Admin\Facilities;

use App\Actions\Facilities\SaveFacility;
use App\Enums\Access\Permission;
use App\Enums\Facilities\FacilityCategory;
use App\Enums\Facilities\InspectionCondition;
use App\Enums\Facilities\OperatingStatus;
use App\Enums\Facilities\Ownership;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Http\Requests\Facilities\SaveFacilityRequest;
use App\Models\Content\MediaItem;
use App\Models\Facilities\Facility;
use App\Models\Facilities\FacilityInspection;
use App\Models\Facilities\FacilityType;
use App\Models\Geography\Community;
use App\Services\Facilities\FacilityDetailRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FacilityController extends Controller
{
    use AuthorizesAreas;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::FacilitiesView);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(FacilityCategory::class)],
            'ownership' => ['nullable', Rule::enum(Ownership::class)],
            'operating_status' => ['nullable', Rule::enum(OperatingStatus::class)],
            'verification_status' => ['nullable', Rule::enum(VerificationStatus::class)],
        ]);

        $facilities = Facility::query()
            ->visibleTo($user)
            ->with(['type:id,name', 'community:id,name', 'town:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('reference_code', 'like', "%{$search}%"),
            ))
            ->when($filters['category'] ?? null, fn (Builder $query, string $value) => $query->where('category', $value))
            ->when($filters['ownership'] ?? null, fn (Builder $query, string $value) => $query->where('ownership', $value))
            ->when($filters['operating_status'] ?? null, fn (Builder $query, string $value) => $query->where('operating_status', $value))
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $value) => $query->where('verification_status', $value))
            ->where('status', '!=', RecordStatus::Archived)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Facility $facility): array => [
                'id' => $facility->id,
                'reference_code' => $facility->reference_code,
                'name' => $facility->name,
                'type' => $facility->type?->name,
                'category' => $facility->category->value,
                'category_label' => $facility->category->label(),
                'ownership' => $facility->ownership->label(),
                'operating_status' => $facility->operating_status->value,
                'verification_status' => $facility->verification_status->value,
                'location' => trim(($facility->community?->name ?? '').' · '.($facility->town?->name ?? ''), ' ·'),
            ]);

        return Inertia::render('admin/facilities/index', [
            'facilities' => $facilities,
            'filters' => array_merge(['search' => '', 'category' => '', 'ownership' => '', 'operating_status' => '', 'verification_status' => ''], $filters),
            'options' => $this->options(),
            'can' => ['create' => $user->can(Permission::FacilitiesManage->value)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::FacilitiesManage);

        return Inertia::render('admin/facilities/form', ['facility' => null, 'options' => $this->options()]);
    }

    public function store(SaveFacilityRequest $request, SaveFacility $save): RedirectResponse
    {
        $community = Community::query()->findOrFail($request->integer('community_id'));
        $this->authorizeArea($request->user(), Permission::FacilitiesManage, $community->pathAttributes());

        if ($request->input('verification_status') !== VerificationStatus::Unverified->value) {
            abort_unless($request->user()->can(Permission::FacilitiesVerify->value), 403, 'You cannot set the verification status.');
        }

        $facility = $save->handle($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Facility registered as :code.', ['code' => $facility->reference_code])]);

        return to_route('admin.facilities.show', $facility);
    }

    public function show(Request $request, Facility $facility): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::FacilitiesView, $facility);

        $facility->load(['type', 'county:id,name', 'district:id,name', 'clan:id,name', 'town:id,name', 'community:id,name', 'block:id,name']);

        return Inertia::render('admin/facilities/show', [
            'facility' => [
                ...$this->present($facility),
                'location' => collect([$facility->county, $facility->district, $facility->clan, $facility->town, $facility->community, $facility->block])->filter()->pluck('name')->all(),
            ],
            'inspections' => $facility->inspections()
                ->with('inspector:id,name')
                ->latest('inspected_on')
                ->latest('id')
                ->limit(50)
                ->get()
                ->map(fn (FacilityInspection $inspection): array => [
                    'id' => $inspection->id,
                    'inspected_on' => $inspection->inspected_on->toDateString(),
                    'inspector' => $inspection->inspector?->name,
                    'operating_status' => $inspection->operating_status->value,
                    'condition' => $inspection->condition->value,
                    'findings' => $inspection->findings,
                    'recommendations' => $inspection->recommendations,
                    'follow_up_due_on' => $inspection->follow_up_due_on?->toDateString(),
                ]),
            'options' => $this->options(),
            'media' => MediaItem::query()
                ->where('mediable_type', $facility->getMorphClass())
                ->where('mediable_id', $facility->id)
                ->orderByDesc('is_featured')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (MediaItem $item): array => $item->toManagerArray()),
            'can' => [
                'update' => $user->can(Permission::FacilitiesManage->value),
                'inspect' => $user->can(Permission::FacilitiesInspect->value),
            ],
        ]);
    }

    public function edit(Request $request, Facility $facility): Response
    {
        $this->authorizeArea($request->user(), Permission::FacilitiesManage, $facility);

        $facility->load(['community:id,name,reference_code', 'block:id,name']);

        return Inertia::render('admin/facilities/form', [
            'facility' => [
                ...$this->present($facility),
                'community' => $facility->community?->only(['id', 'name', 'reference_code']),
                'block' => $facility->block?->only(['id', 'name']),
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(SaveFacilityRequest $request, Facility $facility, SaveFacility $save): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::FacilitiesManage, $facility);
        $this->authorizeArea($user, Permission::FacilitiesManage, Community::query()->findOrFail($request->integer('community_id'))->pathAttributes());

        if ($request->input('verification_status') !== $facility->verification_status->value) {
            abort_unless($user->can(Permission::FacilitiesVerify->value), 403, 'You cannot change the verification status.');
        }

        $save->handle($request->validated(), $facility);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Facility updated.')]);

        return to_route('admin.facilities.show', $facility);
    }

    public function inspect(Request $request, Facility $facility): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::FacilitiesInspect, $facility);

        $validated = $request->validate([
            'inspected_on' => ['required', 'date', 'before_or_equal:today'],
            'operating_status' => ['required', Rule::enum(OperatingStatus::class)],
            'condition' => ['required', Rule::enum(InspectionCondition::class)],
            'findings' => ['required', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'follow_up_due_on' => ['nullable', 'date', 'after:inspected_on'],
            'update_status' => ['boolean'],
        ]);

        $inspection = new FacilityInspection($validated);
        $inspection->forceFill(['facility_id' => $facility->id, 'inspector_id' => $user->id])->save();

        if ($request->boolean('update_status')) {
            $facility->update(['operating_status' => $validated['operating_status']]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inspection recorded.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Facility $facility): array
    {
        return [
            'id' => $facility->id,
            'reference_code' => $facility->reference_code,
            'name' => $facility->name,
            'facility_type_id' => $facility->facility_type_id,
            'type' => $facility->type?->name,
            'category' => $facility->category->value,
            'category_label' => $facility->category->label(),
            'ownership' => $facility->ownership->value,
            'operating_status' => $facility->operating_status->value,
            'community_id' => $facility->community_id,
            'block_id' => $facility->block_id,
            'address_description' => $facility->address_description,
            'latitude' => $facility->latitude,
            'longitude' => $facility->longitude,
            'contact_name' => $facility->contact_name,
            'contact_phone' => $facility->contact_phone,
            'contact_email' => $facility->contact_email,
            'established_year' => $facility->established_year,
            'details' => $facility->details ?? (object) [],
            'notes' => $facility->notes,
            'verification_status' => $facility->verification_status->value,
            'is_public' => $facility->is_public,
            'status' => $facility->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'types' => FacilityType::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'category'])
                ->map(fn (FacilityType $type): array => ['value' => $type->id, 'label' => $type->name, 'category' => $type->category->value]),
            'categories' => FacilityCategory::options(),
            'ownership' => Ownership::options(),
            'operatingStatus' => OperatingStatus::options(),
            'verification' => VerificationStatus::options(),
            'recordStatus' => RecordStatus::options(),
            'conditions' => InspectionCondition::options(),
            'educationLevels' => FacilityDetailRules::EDUCATION_LEVELS,
            'healthServices' => FacilityDetailRules::HEALTH_SERVICES,
            'weekdays' => FacilityDetailRules::WEEKDAYS,
        ];
    }
}
