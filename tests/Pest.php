<?php

use App\Enums\Access\RoleName;
use App\Models\Geography\Block;
use App\Models\Geography\Clan;
use App\Models\Geography\Community;
use App\Models\Geography\County;
use App\Models\Geography\District;
use App\Models\Geography\GeoArea;
use App\Models\Geography\Town;
use App\Models\User;
use App\Services\Geography\GeoAreaResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Seed the permission catalogue and role bundles once per test.
 */
function seedRoles(): void
{
    if (Role::query()->doesntExist()) {
        test()->seed(RolesAndPermissionsSeeder::class);
    }
}

/**
 * A verified, active staff member holding one role, optionally restricted to an area.
 */
function staffUser(RoleName $role, ?GeoArea $scope = null, bool $strongAuth = true): User
{
    seedRoles();

    $user = User::factory()
        ->when($strongAuth, fn ($factory) => $factory->withTwoFactor())
        ->create();
    $user->assignRole($role->value);

    if ($scope !== null) {
        $user->geoScopes()->create(app(GeoAreaResolver::class)->fullPath($scope));
    }

    return $user;
}

/**
 * A complete county → block chain of development fixtures.
 *
 * @return array{county: County, district: District, clan: Clan, town: Town, community: Community, block: Block}
 */
function geoChain(): array
{
    $block = Block::factory()->create();
    $block->load(Block::ancestorRelations());

    return [
        'county' => $block->county,
        'district' => $block->district,
        'clan' => $block->clan,
        'town' => $block->town,
        'community' => $block->community,
        'block' => $block,
    ];
}
