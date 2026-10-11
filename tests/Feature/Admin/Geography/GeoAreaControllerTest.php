<?php

use App\Enums\Access\RoleName;
use App\Models\Geography\Community;
use App\Models\Geography\County;
use App\Models\Geography\District;

test('a national administrator registers a county with an official code', function () {
    $admin = staffUser(RoleName::NationalCensusAdministrator);

    $this->actingAs($admin)
        ->post(route('admin.geography.store', 'county'), [
            'name' => 'Test County',
            'reference_code' => 'TST',
            'status' => 'active',
            'verification_status' => 'unverified',
        ])
        ->assertRedirect();

    $county = County::query()->where('reference_code', 'TST')->sole();

    expect($county->name)->toBe('Test County')
        ->and($county->created_by)->toBe($admin->id)
        ->and($county->is_fixture)->toBeFalse();
});

test('county administrators cannot register counties', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $chain['county']);

    $this->actingAs($admin)
        ->post(route('admin.geography.store', 'county'), [
            'name' => 'Rogue County',
            'reference_code' => 'ROGUE',
            'status' => 'active',
            'verification_status' => 'unverified',
        ])
        ->assertForbidden();
});

test('a district gets a generated reference code and its ancestor path from the parent', function () {
    $county = County::factory()->create(['reference_code' => 'TST']);
    $admin = staffUser(RoleName::CountyAdministrator, $county);

    $this->actingAs($admin)
        ->post(route('admin.geography.store', 'district'), [
            'parent_id' => $county->id,
            'name' => 'District #2',
            'district_number' => 2,
            'status' => 'active',
            'verification_status' => 'unverified',
        ])
        ->assertRedirect();

    $district = District::query()->where('county_id', $county->id)->sole();

    expect($district->reference_code)->toBe('TST-D01')
        ->and($district->district_number)->toBe(2);
});

test('district numbers are unique within a county but may repeat across counties', function () {
    $first = District::factory()->create(['district_number' => 1]);
    $otherCounty = County::factory()->create();
    $admin = staffUser(RoleName::NationalCensusAdministrator);

    $this->actingAs($admin)
        ->post(route('admin.geography.store', 'district'), [
            'parent_id' => $first->county_id,
            'name' => 'Another District',
            'district_number' => 1,
            'status' => 'active',
            'verification_status' => 'unverified',
        ])
        ->assertSessionHasErrors(['district_number' => 'This district number is already used in the county.']);

    $this->actingAs($admin)
        ->post(route('admin.geography.store', 'district'), [
            'parent_id' => $otherCounty->id,
            'name' => 'District #1',
            'district_number' => 1,
            'status' => 'active',
            'verification_status' => 'unverified',
        ])
        ->assertSessionHasNoErrors();
});

test('an exact duplicate name under the same parent is rejected', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::NationalCensusAdministrator);

    $this->actingAs($admin)
        ->post(route('admin.geography.store', 'community'), [
            'parent_id' => $chain['town']->id,
            'name' => '  '.strtoupper($chain['community']->name).' ',
            'status' => 'active',
            'verification_status' => 'unverified',
        ])
        ->assertSessionHasErrors('name');

    expect(Community::query()->where('town_id', $chain['town']->id)->count())->toBe(1);
});

test('a similar name requires explicit confirmation before it is created', function () {
    $chain = geoChain();
    Community::factory()->for($chain['town'])->create(['name' => 'West Point', 'county_id' => $chain['county']->id, 'district_id' => $chain['district']->id, 'clan_id' => $chain['clan']->id]);
    $admin = staffUser(RoleName::NationalCensusAdministrator);
    $payload = [
        'parent_id' => $chain['town']->id,
        'name' => 'Westpoint',
        'status' => 'active',
        'verification_status' => 'unverified',
    ];

    $this->actingAs($admin)
        ->post(route('admin.geography.store', 'community'), $payload)
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)
        ->post(route('admin.geography.store', 'community'), [...$payload, 'confirm_possible_duplicate' => true])
        ->assertSessionHasNoErrors();

    expect(Community::query()->where('name', 'Westpoint')->exists())->toBeTrue();
});

test('administrators cannot edit areas outside their county', function () {
    $mine = geoChain();
    $other = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);

    $this->actingAs($admin)
        ->put(route('admin.geography.update', ['level' => 'community', 'geoArea' => $other['community']->id]), [
            'name' => 'Renamed',
            'status' => 'active',
            'verification_status' => 'verified',
        ])
        ->assertForbidden();

    expect($other['community']->fresh()->name)->not->toBe('Renamed');
});

test('editing an area cannot change its parent', function () {
    $chain = geoChain();
    $otherTown = geoChain()['town'];
    $admin = staffUser(RoleName::NationalCensusAdministrator);

    $this->actingAs($admin)
        ->put(route('admin.geography.update', ['level' => 'community', 'geoArea' => $chain['community']->id]), [
            'parent_id' => $otherTown->id,
            'town_id' => $otherTown->id,
            'name' => $chain['community']->name,
            'status' => 'active',
            'verification_status' => 'verified',
        ])
        ->assertRedirect();

    expect($chain['community']->fresh()->town_id)->toBe($chain['town']->id);
});

test('boundaries must be GeoJSON polygons', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::NationalCensusAdministrator);

    $this->actingAs($admin)
        ->put(route('admin.geography.update', ['level' => 'block', 'geoArea' => $chain['block']->id]), [
            'name' => $chain['block']->name,
            'status' => 'active',
            'verification_status' => 'verified',
            'boundary' => json_encode(['type' => 'Point', 'coordinates' => [-10.8, 6.3]]),
        ])
        ->assertSessionHasErrors(['boundary' => 'Boundary must be a GeoJSON Polygon or MultiPolygon.']);
});

test('the registry pages render with breadcrumbs', function () {
    $chain = geoChain();
    $viewer = staffUser(RoleName::ReportViewer);

    $this->actingAs($viewer)
        ->get(route('admin.geography.show', ['level' => 'block', 'geoArea' => $chain['block']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/geography/show')
            ->where('area.breadcrumb.0.name', $chain['county']->name)
            ->where('area.breadcrumb.5.name', $chain['block']->name));

    $this->actingAs($viewer)
        ->get(route('admin.geography.index', ['level' => 'community', 'search' => $chain['community']->reference_code]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('areas.meta.total', 1));
});

test('area options are limited to the user assignment', function () {
    $mine = geoChain();
    $other = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);

    $this->actingAs($admin)
        ->getJson(route('admin.geography.options', ['level' => 'county']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.value', $mine['county']->id);

    $this->actingAs($admin)
        ->getJson(route('admin.geography.options', ['level' => 'district', 'parent_id' => $other['county']->id]))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
