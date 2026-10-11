<?php

namespace App\Http\Controllers\Admin\Leadership;

use App\Actions\Leadership\RecordLeadershipTenure;
use App\Enums\Access\Permission;
use App\Enums\Leadership\LeadershipLevel;
use App\Enums\Leadership\SelectionMethod;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use App\Models\Leadership\LeadershipPosition;
use App\Models\Leadership\LeadershipTenure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeadershipController extends Controller
{
    use AuthorizesAreas;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::LeadershipView);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'position' => ['nullable', 'integer'],
            'period' => ['nullable', Rule::in(['current', 'former'])],
        ]);

        $tenures = LeadershipTenure::query()
            ->visibleTo($user)
            ->with(['position:id,title', 'community:id,name', 'block:id,name'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $inner) => $inner->where('holder_name', 'like', "%{$search}%")
                    ->orWhereHas('community', fn (Builder $community) => $community->where('name', 'like', "%{$search}%")),
            ))
            ->when($filters['position'] ?? null, fn (Builder $query, int $position) => $query->where('position_id', $position))
            ->when(($filters['period'] ?? null) === 'current', fn (Builder $query) => $query->current())
            ->when(($filters['period'] ?? null) === 'former', fn (Builder $query) => $query->whereNotNull('ends_on')->whereDate('ends_on', '<', now()->toDateString()))
            ->orderBy('community_id')
            ->orderBy('position_id')
            ->orderByDesc('starts_on')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (LeadershipTenure $tenure): array => $this->present($tenure));

        return Inertia::render('admin/leadership/index', [
            'tenures' => $tenures,
            'filters' => array_merge(['search' => '', 'position' => '', 'period' => ''], $filters),
            'positions' => LeadershipPosition::query()->orderBy('sort_order')->get(['id', 'title'])->map(fn ($position): array => ['value' => $position->id, 'label' => $position->title]),
            'can' => [
                'manage' => $user->can(Permission::LeadershipManage->value),
                'configure' => $user->can(Permission::LeadershipManage->value) && $user->can(Permission::GeoNational->value),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::LeadershipManage);

        return Inertia::render('admin/leadership/form', ['tenure' => null, ...$this->options()]);
    }

    public function store(Request $request, RecordLeadershipTenure $record): RedirectResponse
    {
        $user = $request->user();
        $data = $this->validated($request);
        $this->authorizeArea($user, Permission::LeadershipManage, $this->areaPath($data));
        $this->guardVerification($request, null);

        $record->handle($user, $data, null, $request->file('document'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leadership tenure recorded.')]);

        return to_route('admin.leadership.index');
    }

    public function edit(Request $request, LeadershipTenure $tenure): Response
    {
        $this->authorizeArea($request->user(), Permission::LeadershipManage, $tenure);

        $tenure->load(['community:id,name,reference_code', 'block:id,name']);

        return Inertia::render('admin/leadership/form', [
            'tenure' => [
                ...$this->present($tenure),
                'contact_phone' => $tenure->contact_phone,
                'community' => $tenure->community?->only(['id', 'name', 'reference_code']),
                'block' => $tenure->block?->only(['id', 'name']),
            ],
            ...$this->options(),
        ]);
    }

    public function update(Request $request, LeadershipTenure $tenure, RecordLeadershipTenure $record): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::LeadershipManage, $tenure);
        $data = $this->validated($request);
        $this->authorizeArea($user, Permission::LeadershipManage, $this->areaPath($data));
        $this->guardVerification($request, $tenure);

        $record->handle($user, $data, $tenure, $request->file('document'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Leadership tenure updated.')]);

        return to_route('admin.leadership.index');
    }

    public function document(Request $request, LeadershipTenure $tenure): StreamedResponse
    {
        $this->authorizeArea($request->user(), Permission::LeadershipManage, $tenure);
        abort_if($tenure->document_path === null || ! Storage::disk('local')->exists($tenure->document_path), 404);

        return Storage::disk('local')->download($tenure->document_path, $tenure->document_name ?? 'document', [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function positions(Request $request): Response
    {
        $this->authorizeArea($request->user(), [Permission::LeadershipManage]);
        abort_unless($request->user()->can(Permission::GeoNational->value), 403);

        return Inertia::render('admin/leadership/positions', [
            'positions' => LeadershipPosition::query()->orderBy('level')->orderBy('sort_order')->get()->map(fn (LeadershipPosition $position): array => [
                'id' => $position->id,
                'code' => $position->code,
                'title' => $position->title,
                'level' => $position->level->value,
                'single_holder' => $position->single_holder,
                'is_active' => $position->is_active,
                'sort_order' => $position->sort_order,
            ]),
            'levels' => LeadershipLevel::options(),
        ]);
    }

    public function savePosition(Request $request, ?LeadershipPosition $position = null): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::LeadershipManage);
        abort_unless($request->user()->can(Permission::GeoNational->value), 403);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', Rule::unique('leadership_positions', 'code')->ignore($position?->id)],
            'title' => ['required', 'string', 'max:120'],
            'level' => ['required', Rule::enum(LeadershipLevel::class)],
            'single_holder' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'between:0,1000'],
        ]);

        if ($position?->exists && $position->tenures()->exists() && $validated['level'] !== $position->level->value) {
            return back()->withErrors(['level' => 'The level cannot change once tenures exist for this position.']);
        }

        ($position ?? new LeadershipPosition)->fill([...$validated, 'sort_order' => $validated['sort_order'] ?? 0])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Position saved.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'position_id' => ['required', 'integer', Rule::exists('leadership_positions', 'id')->where('is_active', true)],
            'community_id' => ['nullable', 'integer', Rule::exists('communities', 'id'), 'required_without:block_id'],
            'block_id' => ['nullable', 'integer', Rule::exists('blocks', 'id')],
            'holder_name' => ['required', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'selection_method' => ['required', Rule::enum(SelectionMethod::class)],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'is_public' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'exception_note' => ['nullable', 'string', 'max:2000'],
            'close_previous' => ['boolean'],
            'document' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|null>
     */
    private function areaPath(array $data): array
    {
        if (! empty($data['block_id'])) {
            return Block::query()->findOrFail($data['block_id'])->pathAttributes();
        }

        return Community::query()->findOrFail($data['community_id'])->pathAttributes();
    }

    private function guardVerification(Request $request, ?LeadershipTenure $tenure): void
    {
        $current = $tenure?->verification_status->value ?? VerificationStatus::Unverified->value;

        if ($request->input('verification_status') !== $current) {
            abort_unless($request->user()->can(Permission::LeadershipVerify->value), 403, 'You cannot change the verification status.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function present(LeadershipTenure $tenure): array
    {
        return [
            'id' => $tenure->id,
            'position_id' => $tenure->position_id,
            'position' => $tenure->position?->title,
            'level' => $tenure->level->value,
            'community_id' => $tenure->community_id,
            'community_name' => $tenure->community?->name,
            'block_id' => $tenure->block_id,
            'block_name' => $tenure->block?->name,
            'holder_name' => $tenure->holder_name,
            'starts_on' => $tenure->starts_on->toDateString(),
            'ends_on' => $tenure->ends_on?->toDateString(),
            'is_current' => $tenure->isCurrent(),
            'selection_method' => $tenure->selection_method->value,
            'verification_status' => $tenure->verification_status->value,
            'is_public' => $tenure->is_public,
            'has_exception' => $tenure->exception_note !== null,
            'exception_note' => $tenure->exception_note,
            'has_document' => $tenure->document_path !== null,
            'notes' => $tenure->notes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'positions' => LeadershipPosition::query()->where('is_active', true)->orderBy('level')->orderBy('sort_order')->get()
                ->map(fn (LeadershipPosition $position): array => ['value' => $position->id, 'label' => $position->title, 'level' => $position->level->value, 'single_holder' => $position->single_holder]),
            'selectionMethods' => SelectionMethod::options(),
            'verification' => VerificationStatus::options(),
        ];
    }
}
