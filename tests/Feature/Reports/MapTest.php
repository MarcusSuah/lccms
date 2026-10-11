<?php

use App\Enums\Access\RoleName;
use App\Enums\VerificationStatus;
use App\Models\Census\House;
use App\Models\Facilities\Facility;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
});

test('staff map layers follow permissions and geographic reach', function () {
    $mine = geoChain();
    $other = geoChain();
    $visible = Facility::factory()->inCommunity($mine['community'])->create(['latitude' => 6.3, 'longitude' => -10.8]);
    Facility::factory()->inCommunity($other['community'])->create(['latitude' => 7.1, 'longitude' => -9.5]);
    $inspector = staffUser(RoleName::FacilityInspector, $mine['county']);

    $this->actingAs($inspector)->get(route('admin.map.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/map')
            ->where('layers', fn ($layers) => collect($layers)->pluck('key')->all() === ['communities', 'facilities', 'infrastructure']));

    $this->actingAs($inspector)->getJson(route('admin.map.points', ['layer' => 'facilities']))
        ->assertOk()
        ->assertJsonCount(1, 'points')
        ->assertJsonPath('points.0.id', $visible->id)
        ->assertJsonPath('truncated', false);

    $this->actingAs($inspector)->getJson(route('admin.map.points', ['layer' => 'houses']))->assertForbidden();
});

test('house locations are shown only to users who may see census personal data', function () {
    $chain = geoChain();
    $house = House::factory()->onBlock($chain['block'])->create(['latitude' => 6.31, 'longitude' => -10.79]);
    $blockLeader = staffUser(RoleName::BlockLeader, $chain['block']);
    $countyAdmin = staffUser(RoleName::CountyAdministrator, $chain['county']);

    $this->actingAs($blockLeader)->getJson(route('admin.map.points', ['layer' => 'houses']))->assertForbidden();

    $this->actingAs($countyAdmin)->getJson(route('admin.map.points', ['layer' => 'houses']))
        ->assertOk()
        ->assertJsonPath('points.0.label', $house->reference_code);
});

test('the public map shows only published verified communities and public verified facilities', function () {
    $chain = geoChain();
    $chain['community']->forceFill([
        'is_published' => true,
        'verification_status' => VerificationStatus::Verified,
        'latitude' => 6.3,
        'longitude' => -10.8,
    ])->save();
    $public = Facility::factory()->inCommunity($chain['community'])->verified()->create(['is_public' => true, 'latitude' => 6.31, 'longitude' => -10.81]);
    Facility::factory()->inCommunity($chain['community'])->create(['is_public' => true, 'latitude' => 6.32, 'longitude' => -10.82]);
    Facility::factory()->inCommunity($chain['community'])->verified()->create(['is_public' => false, 'latitude' => 6.33, 'longitude' => -10.83]);
    House::factory()->onBlock($chain['block'])->create(['latitude' => 6.34, 'longitude' => -10.84]);

    $this->get(route('map'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/map')
            ->has('points.communities', 1)
            ->where('points.communities.0.label', $chain['community']->name)
            ->has('points.facilities', 1)
            ->where('points.facilities.0.id', $public->id)
            ->missing('points.houses'));
});
