<?php

use App\Enums\Access\RoleName;
use App\Enums\Geography\ReassignmentStatus;
use App\Models\Geography\Block;
use App\Models\Geography\GeoReassignment;
use App\Models\Geography\Town;

function requestCommunityMove(array $chain, Town $destination, $requester): GeoReassignment
{
    test()->actingAs($requester)
        ->post(route('admin.geography.reassignments.store', ['level' => 'community', 'geoArea' => $chain['community']->id]), [
            'to_parent_id' => $destination->id,
            'reason' => 'Boundary commission decision 2026/14',
            'effective_on' => now()->toDateString(),
        ])
        ->assertSessionHasNoErrors();

    return GeoReassignment::query()->latest('id')->firstOrFail();
}

test('a reassignment is not applied until approved', function () {
    $chain = geoChain();
    $destination = Town::factory()->create();
    $requester = staffUser(RoleName::NationalCensusAdministrator);

    $reassignment = requestCommunityMove($chain, $destination, $requester);

    expect($reassignment->status)->toBe(ReassignmentStatus::Pending)
        ->and($chain['community']->fresh()->town_id)->toBe($chain['town']->id);
});

test('approving a move rewrites the path of the area, its descendants and staff scopes', function () {
    $chain = geoChain();
    $destination = Town::factory()->create();
    $requester = staffUser(RoleName::NationalCensusAdministrator);
    $approver = staffUser(RoleName::NationalCensusAdministrator);
    $blockLeader = staffUser(RoleName::BlockLeader, $chain['block']);

    $reassignment = requestCommunityMove($chain, $destination, $requester);

    $this->actingAs($approver)
        ->patch(route('admin.geography.reassignments.update', $reassignment), ['decision' => 'approve'])
        ->assertSessionHasNoErrors();

    $community = $chain['community']->fresh();
    $block = Block::query()->findOrFail($chain['block']->id);

    expect($community->town_id)->toBe($destination->id)
        ->and($community->county_id)->toBe($destination->county_id)
        ->and($block->town_id)->toBe($destination->id)
        ->and($block->district_id)->toBe($destination->district_id)
        ->and($blockLeader->geoScopes()->sole()->town_id)->toBe($destination->id)
        ->and($reassignment->fresh()->status)->toBe(ReassignmentStatus::Applied);
});

test('the requester cannot approve their own reassignment', function () {
    $chain = geoChain();
    $destination = Town::factory()->create();
    $requester = staffUser(RoleName::NationalCensusAdministrator);

    $reassignment = requestCommunityMove($chain, $destination, $requester);

    $this->actingAs($requester)
        ->patch(route('admin.geography.reassignments.update', $reassignment), ['decision' => 'approve'])
        ->assertSessionHasErrors(['decision' => 'A reassignment must be approved by someone other than the requester.']);

    expect($chain['community']->fresh()->town_id)->toBe($chain['town']->id);
});

test('rejecting a reassignment requires a note and leaves the area in place', function () {
    $chain = geoChain();
    $destination = Town::factory()->create();
    $requester = staffUser(RoleName::NationalCensusAdministrator);
    $approver = staffUser(RoleName::NationalCensusAdministrator);
    $reassignment = requestCommunityMove($chain, $destination, $requester);

    $this->actingAs($approver)
        ->patch(route('admin.geography.reassignments.update', $reassignment), ['decision' => 'reject'])
        ->assertSessionHasErrors('note');

    $this->actingAs($approver)
        ->patch(route('admin.geography.reassignments.update', $reassignment), ['decision' => 'reject', 'note' => 'Insufficient evidence'])
        ->assertSessionHasNoErrors();

    expect($reassignment->fresh()->status)->toBe(ReassignmentStatus::Rejected)
        ->and($chain['community']->fresh()->town_id)->toBe($chain['town']->id);
});

test('county administrators cannot move areas into another county', function () {
    $chain = geoChain();
    $foreignTown = Town::factory()->create();
    $admin = staffUser(RoleName::CountyAdministrator, $chain['county']);

    $this->actingAs($admin)
        ->post(route('admin.geography.reassignments.store', ['level' => 'community', 'geoArea' => $chain['community']->id]), [
            'to_parent_id' => $foreignTown->id,
            'reason' => 'Boundary commission decision 2026/14',
            'effective_on' => now()->toDateString(),
        ])
        ->assertSessionHasErrors(['to_parent_id' => 'You can only move areas into parents within your assignment.']);

    expect(GeoReassignment::query()->count())->toBe(0);
});
