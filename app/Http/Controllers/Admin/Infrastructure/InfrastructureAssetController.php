<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Enums\Access\Permission;
use App\Enums\Infrastructure\AssetCondition;
use App\Enums\Infrastructure\Availability;
use App\Enums\Infrastructure\InfrastructureCategory;
use App\Enums\RecordStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use App\Models\Infrastructure\InfrastructureAsset;
use App\Services\SequenceCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InfrastructureAssetController extends Controller
{
    use AuthorizesAreas;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::InfrastructureView);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::enum(InfrastructureCategory::class)],
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
        ]);

        $assets = InfrastructureAsset::query()
            ->visibleTo($user)
            ->with(['community:id,name', 'town:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('reference_code', 'like', "%{$search}%"),
            ))
            ->when($filters['category'] ?? null, fn (Builder $query, string $value) => $query->where('category', $value))
            ->when($filters['condition'] ?? null, fn (Builder $query, string $value) => $query->where('condition', $value))
            ->where('status', '!=', RecordStatus::Archived)
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (InfrastructureAsset $asset): array => [
                ...$this->present($asset),
                'location' => trim(($asset->community?->name ?? '').' · '.($asset->town?->name ?? ''), ' ·'),
            ]);

        return Inertia::render('admin/infrastructure/index', [
            'assets' => $assets,
            'filters' => array_merge(['search' => '', 'category' => '', 'condition' => ''], $filters),
            'options' => $this->options(),
            'can' => ['manage' => $user->can(Permission::InfrastructureManage->value)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::InfrastructureManage);

        return Inertia::render('admin/infrastructure/form', ['asset' => null, 'options' => $this->options()]);
    }

    public function store(Request $request, SequenceCodes $codes): RedirectResponse
    {
        $data = $this->validated($request);
        [$community, $block] = $this->area($data);
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $community->pathAttributes());

        DB::transaction(function () use ($data, $community, $block, $codes): void {
            $asset = new InfrastructureAsset(Arr::except($data, ['community_id', 'block_id']));
            $asset->forceFill([
                ...$community->pathAttributes(),
                'block_id' => $block?->id,
                'reference_code' => $codes->next(InfrastructureAsset::class, $community->reference_code.'-I'),
            ])->save();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Infrastructure asset recorded.')]);

        return to_route('admin.infrastructure.index');
    }

    public function edit(Request $request, InfrastructureAsset $asset): Response
    {
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $asset);
        $asset->load(['community:id,name,reference_code', 'block:id,name']);

        return Inertia::render('admin/infrastructure/form', [
            'asset' => [
                ...$this->present($asset),
                'community' => $asset->community?->only(['id', 'name', 'reference_code']),
                'block' => $asset->block?->only(['id', 'name']),
            ],
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, InfrastructureAsset $asset): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $asset);
        $data = $this->validated($request);
        [$community, $block] = $this->area($data);
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $community->pathAttributes());

        $asset->fill(Arr::except($data, ['community_id', 'block_id']));
        $asset->forceFill([...$community->pathAttributes(), 'block_id' => $block?->id])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Infrastructure asset updated.')]);

        return to_route('admin.infrastructure.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', Rule::enum(InfrastructureCategory::class)],
            'community_id' => ['required', 'integer', Rule::exists('communities', 'id')],
            'block_id' => ['nullable', 'integer', Rule::exists('blocks', 'id')],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            'availability' => ['required', Rule::enum(Availability::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'last_inspected_on' => ['nullable', 'date', 'before_or_equal:today'],
            'maintenance_needs' => ['nullable', 'string', 'max:5000'],
            'responsible_organization' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'is_public' => ['boolean'],
            'status' => ['required', Rule::enum(RecordStatus::class)],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: Community, 1: Block|null}
     */
    private function area(array $data): array
    {
        $community = Community::query()->findOrFail($data['community_id']);
        $block = empty($data['block_id']) ? null : Block::query()->findOrFail($data['block_id']);

        abort_if($block !== null && $block->community_id !== $community->id, 422, 'The block is not in the selected community.');

        return [$community, $block];
    }

    /**
     * @return array<string, mixed>
     */
    private function present(InfrastructureAsset $asset): array
    {
        return [
            'id' => $asset->id,
            'reference_code' => $asset->reference_code,
            'name' => $asset->name,
            'category' => $asset->category->value,
            'category_label' => $asset->category->label(),
            'community_id' => $asset->community_id,
            'block_id' => $asset->block_id,
            'condition' => $asset->condition->value,
            'availability' => $asset->availability->value,
            'latitude' => $asset->latitude,
            'longitude' => $asset->longitude,
            'last_inspected_on' => $asset->last_inspected_on?->toDateString(),
            'maintenance_needs' => $asset->maintenance_needs,
            'responsible_organization' => $asset->responsible_organization,
            'notes' => $asset->notes,
            'verification_status' => $asset->verification_status->value,
            'is_public' => $asset->is_public,
            'status' => $asset->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'categories' => InfrastructureCategory::options(),
            'conditions' => AssetCondition::options(),
            'availability' => Availability::options(),
            'verification' => VerificationStatus::options(),
            'recordStatus' => RecordStatus::options(),
        ];
    }
}
