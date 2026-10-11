<?php

namespace App\Http\Controllers\Admin\Infrastructure;

use App\Enums\Access\Permission;
use App\Enums\Geography\GeoLevel;
use App\Enums\Infrastructure\ProjectCategory;
use App\Enums\Infrastructure\ProjectStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Concerns\AuthorizesAreas;
use App\Http\Controllers\Controller;
use App\Models\Infrastructure\DevelopmentProject;
use App\Models\Infrastructure\ProjectMilestone;
use App\Models\Infrastructure\ProjectProgressReport;
use App\Services\Geography\GeoAreaResolver;
use App\Services\SequenceCodes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DevelopmentProjectController extends Controller
{
    use AuthorizesAreas;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::InfrastructureView);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            'category' => ['nullable', Rule::enum(ProjectCategory::class)],
        ]);

        $projects = DevelopmentProject::query()
            ->visibleTo($user)
            ->with(DevelopmentProject::areaRelations())
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(
                fn (Builder $inner) => $inner->where('title', 'like', "%{$search}%")->orWhere('reference_code', 'like', "%{$search}%"),
            ))
            ->when($filters['status'] ?? null, fn (Builder $query, string $value) => $query->where('status', $value))
            ->when($filters['category'] ?? null, fn (Builder $query, string $value) => $query->where('category', $value))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (DevelopmentProject $project): array => $this->present($project));

        return Inertia::render('admin/projects/index', [
            'projects' => $projects,
            'filters' => array_merge(['search' => '', 'status' => '', 'category' => ''], $filters),
            'options' => $this->options(),
            'can' => ['manage' => $user->can(Permission::InfrastructureManage->value)],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorizeArea($request->user(), Permission::InfrastructureManage);

        return Inertia::render('admin/projects/form', ['project' => null, 'options' => $this->options()]);
    }

    public function store(Request $request, GeoAreaResolver $resolver, SequenceCodes $codes): RedirectResponse
    {
        $data = $this->validated($request);
        $path = $this->path($data, $resolver);
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $path);

        $project = DB::transaction(function () use ($data, $path, $codes): DevelopmentProject {
            $project = new DevelopmentProject(Arr::except($data, ['level', 'area_id']));
            $project->forceFill([...$path, 'reference_code' => $codes->next(DevelopmentProject::class, 'PRJ-'.now()->year.'-', 4)])->save();

            return $project;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project :code created.', ['code' => $project->reference_code])]);

        return to_route('admin.projects.show', $project);
    }

    public function show(Request $request, DevelopmentProject $project): Response
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::InfrastructureView, $project);
        $project->load([...DevelopmentProject::areaRelations(), 'milestones', 'progressReports.reporter:id,name']);

        return Inertia::render('admin/projects/show', [
            'project' => [
                ...$this->present($project),
                'description' => $project->description,
                'milestones' => $project->milestones->map(fn (ProjectMilestone $milestone): array => [
                    'id' => $milestone->id,
                    'title' => $milestone->title,
                    'due_on' => $milestone->due_on?->toDateString(),
                    'completed_on' => $milestone->completed_on?->toDateString(),
                    'notes' => $milestone->notes,
                ]),
                'reports' => $project->progressReports->map(fn (ProjectProgressReport $report): array => [
                    'id' => $report->id,
                    'reported_on' => $report->reported_on->toDateString(),
                    'progress_percent' => $report->progress_percent,
                    'summary' => $report->summary,
                    'reporter' => $report->reporter?->name,
                ]),
            ],
            'can' => ['manage' => $user->can(Permission::InfrastructureManage->value)],
        ]);
    }

    public function edit(Request $request, DevelopmentProject $project): Response
    {
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $project);
        $project->load(DevelopmentProject::areaRelations());

        return Inertia::render('admin/projects/form', ['project' => $this->present($project), 'options' => $this->options()]);
    }

    public function update(Request $request, DevelopmentProject $project, GeoAreaResolver $resolver): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $project);
        $data = $this->validated($request);
        $path = $this->path($data, $resolver);
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $path);

        $project->fill(Arr::except($data, ['level', 'area_id']))->forceFill($path)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Project updated.')]);

        return to_route('admin.projects.show', $project);
    }

    public function storeMilestone(Request $request, DevelopmentProject $project): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $project);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_on' => ['nullable', 'date'],
            'completed_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $project->milestones()->create([...$validated, 'sort_order' => $project->milestones()->count() + 1]);

        return back();
    }

    public function updateMilestone(Request $request, DevelopmentProject $project, ProjectMilestone $milestone): RedirectResponse
    {
        $this->authorizeArea($request->user(), Permission::InfrastructureManage, $project);
        abort_unless($milestone->project_id === $project->id, 404);

        $milestone->update($request->validate([
            'completed_on' => ['nullable', 'date'],
        ]));

        return back();
    }

    public function storeReport(Request $request, DevelopmentProject $project): RedirectResponse
    {
        $user = $request->user();
        $this->authorizeArea($user, Permission::InfrastructureManage, $project);

        $validated = $request->validate([
            'reported_on' => ['required', 'date', 'before_or_equal:today'],
            'progress_percent' => ['required', 'integer', 'between:0,100'],
            'summary' => ['required', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($project, $validated, $user): void {
            $project->progressReports()->create([...$validated, 'reported_by' => $user->id]);
            $project->update(['progress_percent' => $validated['progress_percent']]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Progress report added.')]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'category' => ['required', Rule::enum(ProjectCategory::class)],
            'status' => ['required', Rule::enum(ProjectStatus::class)],
            'level' => ['required', Rule::enum(GeoLevel::class)],
            'area_id' => ['required', 'integer', 'min:1'],
            'start_date' => ['nullable', 'date'],
            'target_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'actual_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'sponsor' => ['nullable', 'string', 'max:255'],
            'responsible_organization' => ['nullable', 'string', 'max:255'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'budget_currency' => ['nullable', 'required_with:budget_amount', Rule::in(['LRD', 'USD'])],
            'budget_is_public' => ['boolean'],
            'progress_percent' => ['nullable', 'integer', 'between:0,100'],
            'verification_status' => ['required', Rule::enum(VerificationStatus::class)],
            'is_public' => ['boolean'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, int|null>
     */
    private function path(array $data, GeoAreaResolver $resolver): array
    {
        $area = $resolver->find(GeoLevel::from($data['level']), (int) $data['area_id']);
        abort_if($area === null, 422, 'The selected area does not exist.');

        return $resolver->fullPath($area);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(DevelopmentProject $project): array
    {
        return [
            'id' => $project->id,
            'reference_code' => $project->reference_code,
            'title' => $project->title,
            'description' => $project->description,
            'category' => $project->category->value,
            'category_label' => $project->category->label(),
            'status' => $project->status->value,
            'level' => $project->level()->value,
            'area_id' => $project->areaId(),
            'area' => $project->areaLabel(),
            'start_date' => $project->start_date?->toDateString(),
            'target_end_date' => $project->target_end_date?->toDateString(),
            'actual_end_date' => $project->actual_end_date?->toDateString(),
            'sponsor' => $project->sponsor,
            'responsible_organization' => $project->responsible_organization,
            'budget_amount' => $project->budget_amount,
            'budget_currency' => $project->budget_currency,
            'budget_is_public' => $project->budget_is_public,
            'progress_percent' => $project->progress_percent,
            'verification_status' => $project->verification_status->value,
            'is_public' => $project->is_public,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function options(): array
    {
        return [
            'categories' => ProjectCategory::options(),
            'statuses' => ProjectStatus::options(),
            'verification' => VerificationStatus::options(),
            'levels' => GeoLevel::options(),
        ];
    }
}
