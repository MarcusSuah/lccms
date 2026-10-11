<?php

namespace App\Http\Controllers\Admin\Access;

use App\Actions\Access\SaveStaffUser;
use App\Enums\Access\RoleName;
use App\Enums\Geography\GeoLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\Access\SaveUserRequest;
use App\Http\Resources\Access\UserResource;
use App\Models\User;
use App\Services\Access\RoleAssignmentRules;
use App\Services\Geography\GeoScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Eager loads needed to render scope labels without N+1 queries.
     *
     * @var list<string>
     */
    private const SCOPE_RELATIONS = [
        'geoScopes.county', 'geoScopes.district', 'geoScopes.clan',
        'geoScopes.town', 'geoScopes.community', 'geoScopes.block',
    ];

    public function index(Request $request, GeoScope $geoScope): Response
    {
        Gate::authorize('viewAny', User::class);

        $actor = $request->user();
        $search = $request->string('search')->trim()->toString();
        $role = $request->string('role')->toString();

        $users = User::query()
            ->with(['roles', ...self::SCOPE_RELATIONS])
            ->when(! $geoScope->for($actor)->national, function (Builder $query) use ($actor, $geoScope): void {
                $query->whereHas('geoScopes', fn (Builder $scopes) => $geoScope->apply($scopes, $actor));
            })
            ->when($search !== '', fn (Builder $query) => $query->where(
                fn (Builder $inner) => $inner->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"),
            ))
            ->when(RoleName::tryFrom($role) !== null, fn (Builder $query) => $query->role($role))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/users/index', [
            'users' => UserResource::collection($users),
            'filters' => ['search' => $search, 'role' => $role],
            'roleOptions' => RoleName::options(),
            'can' => ['create' => $actor->can('create', User::class)],
        ]);
    }

    public function create(Request $request, RoleAssignmentRules $roleRules): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('admin/users/form', [
            'user' => null,
            ...$this->formOptions($request->user(), $roleRules),
        ]);
    }

    public function store(SaveUserRequest $request, SaveStaffUser $save): RedirectResponse
    {
        Gate::authorize('create', User::class);

        $user = $save->handle($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account created and invitation sent to :email.', ['email' => $user->email])]);

        return to_route('admin.users.index');
    }

    public function edit(Request $request, User $user, RoleAssignmentRules $roleRules): Response
    {
        Gate::authorize('update', $user);

        $user->load(['roles', ...self::SCOPE_RELATIONS]);

        return Inertia::render('admin/users/form', [
            'user' => new UserResource($user),
            ...$this->formOptions($request->user(), $roleRules),
        ]);
    }

    public function update(SaveUserRequest $request, User $user, SaveStaffUser $save): RedirectResponse
    {
        Gate::authorize('update', $user);

        $save->handle($request->user(), $request->validated(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Account updated.')]);

        return to_route('admin.users.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(User $actor, RoleAssignmentRules $roleRules): array
    {
        return [
            'roleOptions' => array_map(
                fn (RoleName $role): array => ['value' => $role->value, 'label' => $role->label()],
                $roleRules->assignableBy($actor),
            ),
            'levelOptions' => GeoLevel::options(),
        ];
    }
}
