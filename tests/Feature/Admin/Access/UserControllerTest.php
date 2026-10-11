<?php

use App\Enums\Access\RoleName;
use App\Enums\Geography\GeoLevel;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;

test('guests are redirected to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('enumerators cannot open user administration', function () {
    $chain = geoChain();
    $enumerator = staffUser(RoleName::CensusEnumerator, $chain['community']);

    $this->actingAs($enumerator)->get(route('admin.users.index'))->assertForbidden();
});

test('a county administrator only lists staff assigned inside their county', function () {
    $mine = geoChain();
    $other = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);
    $colleague = staffUser(RoleName::CensusEnumerator, $mine['block']);
    $outsider = staffUser(RoleName::CensusEnumerator, $other['block']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->where('users.data', fn ($users) => collect($users)->pluck('id')->contains($colleague->id)
                && ! collect($users)->pluck('id')->contains($outsider->id)));
});

test('a county administrator creates an enumerator in their county and an invitation is sent', function () {
    Notification::fake();
    $chain = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $chain['county']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'New Enumerator',
            'email' => 'new.enumerator@example.test',
            'roles' => [RoleName::CensusEnumerator->value],
            'scopes' => [['level' => GeoLevel::Community->value, 'area_id' => $chain['community']->id]],
        ])
        ->assertRedirect(route('admin.users.index'));

    $created = User::query()->where('email', 'new.enumerator@example.test')->firstOrFail();

    expect($created->hasRole(RoleName::CensusEnumerator->value))->toBeTrue()
        ->and($created->email_verified_at)->toBeNull()
        ->and($created->created_by)->toBe($admin->id)
        ->and($created->geoScopes()->sole()->path())->toBe([
            'county_id' => $chain['county']->id,
            'district_id' => $chain['district']->id,
            'clan_id' => $chain['clan']->id,
            'town_id' => $chain['town']->id,
            'community_id' => $chain['community']->id,
            'block_id' => null,
        ]);

    Notification::assertSentTo($created, ResetPassword::class);
});

test('administrators cannot grant access outside their own area', function () {
    $mine = geoChain();
    $other = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Escalation Attempt',
            'email' => 'escalation@example.test',
            'roles' => [RoleName::CensusEnumerator->value],
            'scopes' => [['level' => GeoLevel::County->value, 'area_id' => $other['county']->id]],
        ])
        ->assertSessionHasErrors(['scopes.0.area_id' => 'You can only grant access to areas within your own assignment.']);

    expect(User::query()->where('email', 'escalation@example.test')->exists())->toBeFalse();
});

test('administrators cannot grant roles above their own', function (RoleName $role) {
    $chain = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $chain['county']);

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Escalation Attempt',
            'email' => 'escalation@example.test',
            'roles' => [$role->value],
            'scopes' => [['level' => GeoLevel::County->value, 'area_id' => $chain['county']->id]],
        ])
        ->assertSessionHasErrors(['roles.0' => 'You are not allowed to grant this role.']);
})->with([
    'super administrator' => [RoleName::SystemSuperAdministrator],
    'national administrator' => [RoleName::NationalCensusAdministrator],
]);

test('a county administrator cannot edit staff in another county', function () {
    $mine = geoChain();
    $other = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);
    $outsider = staffUser(RoleName::CensusEnumerator, $other['block']);

    $this->actingAs($admin)->get(route('admin.users.edit', $outsider))->assertForbidden();
    $this->actingAs($admin)->put(route('admin.users.update', $outsider), [
        'name' => 'Hijacked',
        'email' => $outsider->email,
        'roles' => [RoleName::CensusEnumerator->value],
        'scopes' => [['level' => GeoLevel::County->value, 'area_id' => $mine['county']->id]],
    ])->assertForbidden();

    expect($outsider->fresh()->name)->not->toBe('Hijacked');
});

test('deactivating an account revokes its API tokens and blocks sign-in', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $chain['county']);
    $enumerator = staffUser(RoleName::CensusEnumerator, $chain['block']);
    $enumerator->createToken('field-device');

    $this->actingAs($admin)->put(route('admin.users.update', $enumerator), [
        'name' => $enumerator->name,
        'email' => $enumerator->email,
        'is_active' => false,
        'roles' => [RoleName::CensusEnumerator->value],
        'scopes' => [['level' => GeoLevel::Block->value, 'area_id' => $chain['block']->id]],
    ])->assertRedirect(route('admin.users.index'));

    expect($enumerator->fresh()->is_active)->toBeFalse()
        ->and($enumerator->tokens()->count())->toBe(0);

    auth()->logout();

    $this->post(route('login.store'), ['email' => $enumerator->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'This account has been deactivated. Contact your administrator.']);
    $this->assertGuest();
});

test('a deactivated user with an open session is signed out', function () {
    $user = User::factory()->deactivated()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('privileged roles must enrol strong authentication before administration', function () {
    $admin = staffUser(RoleName::NationalCensusAdministrator, strongAuth: false);

    $this->actingAs($admin)->get(route('admin.users.index'))->assertRedirect(route('security.edit'));
});

test('privileged roles reach administration without strong authentication while it is switched off', function () {
    config(['lccms.security.strong_authentication' => false]);
    $admin = staffUser(RoleName::NationalCensusAdministrator, strongAuth: false);

    $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
});

test('two-factor and passkeys are hidden and not challenged while strong authentication is switched off', function () {
    config(['lccms.security.strong_authentication' => false]);
    $user = User::factory()->withTwoFactor()->create();

    $this->get(route('login'))->assertInertia(fn ($page) => $page->where('canUsePasskeys', false));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn ($page) => $page->where('canManageTwoFactor', false)->where('canManagePasskeys', false));
});
