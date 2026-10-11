<?php

use App\Enums\Access\RoleName;
use App\Enums\Geography\GeoLevel;
use App\Models\Geography\Block;
use App\Models\Geography\Community;
use App\Services\Geography\GeoScope;
use Illuminate\Database\QueryException;

test('a county-scoped user only sees records in their county', function () {
    $mine = geoChain();
    geoChain();
    $user = staffUser(RoleName::CountyAdministrator, $mine['county']);

    $visible = Block::query()->visibleTo($user)->pluck('id');

    expect($visible->all())->toBe([$mine['block']->id]);
});

test('a block-scoped user cannot see sibling blocks in the same community', function () {
    $chain = geoChain();
    $sibling = Block::factory()->for($chain['community'])->create();
    $user = staffUser(RoleName::BlockLeader, $chain['block']);

    $visible = Block::query()->visibleTo($user)->pluck('id')->all();

    expect($visible)->toBe([$chain['block']->id])
        ->and(app(GeoScope::class)->allows($user, $sibling))->toBeFalse()
        ->and(app(GeoScope::class)->allows($user, $chain['block']))->toBeTrue();
});

test('a user without any scope sees nothing', function () {
    geoChain();
    $user = staffUser(RoleName::CountyAdministrator);

    expect(Block::query()->visibleTo($user)->count())->toBe(0);
});

test('national roles see every area', function () {
    geoChain();
    geoChain();
    $user = staffUser(RoleName::NationalCensusAdministrator);

    expect(Block::query()->visibleTo($user)->count())->toBe(2);
});

test('expired scopes grant no access', function () {
    $chain = geoChain();
    $user = staffUser(RoleName::CountyAdministrator);
    $user->geoScopes()->create([
        ...$chain['county']->pathAttributes(),
        'ends_on' => now()->subDay()->toDateString(),
    ]);

    expect(Block::query()->visibleTo($user)->count())->toBe(0);
});

test('area pickers show the path down to a scope and everything beneath it', function () {
    $chain = geoChain();
    $otherCommunityInTown = Community::factory()->for($chain['town'])->create();
    $user = staffUser(RoleName::CommunityAdministrator, $chain['community']);
    $geoScope = app(GeoScope::class);

    $counties = $geoScope->applyToLevel(GeoLevel::County->modelClass()::query(), GeoLevel::County, $user)->pluck('id')->all();
    $communities = $geoScope->applyToLevel(Community::query(), GeoLevel::Community, $user)->pluck('id')->all();
    $blocks = $geoScope->applyToLevel(Block::query(), GeoLevel::Block, $user)->pluck('id')->all();

    expect($counties)->toBe([$chain['county']->id])
        ->and($communities)->toBe([$chain['community']->id])
        ->and($communities)->not->toContain($otherCommunityInTown->id)
        ->and($blocks)->toBe([$chain['block']->id]);
});

test('the scope path must be contiguous', function () {
    $chain = geoChain();
    $user = staffUser(RoleName::BlockLeader);

    expect(fn () => $user->geoScopes()->create([
        'county_id' => $chain['county']->id,
        'block_id' => $chain['block']->id,
    ]))->toThrow(QueryException::class, 'user_geo_scopes_contiguous_path');
});
