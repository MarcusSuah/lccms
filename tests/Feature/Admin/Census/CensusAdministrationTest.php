<?php

use App\Actions\Census\CensusWorkflow;
use App\Actions\Census\RegisterHouse;
use App\Actions\Census\SaveHousehold;
use App\Actions\Census\SaveOccupant;
use App\Enums\Access\RoleName;
use App\Enums\Census\CampaignStatus;
use App\Enums\Census\CensusRecordStatus;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\Census\Household;

/**
 * @return array<string, mixed>
 */
function adminScenario(): array
{
    $chain = geoChain();
    $campaign = CensusCampaign::factory()->active()->covering($chain['county'])->create();
    $enumerator = staffUser(RoleName::CensusEnumerator);
    CampaignAssignment::factory()->for($campaign, 'campaign')->for($enumerator)->inArea($chain['community'])->create();

    $house = app(RegisterHouse::class)->create($enumerator, $campaign, $chain['block'], []);
    $household = app(SaveHousehold::class)->create($enumerator, $campaign, $house, ['residence_type' => 'usual', 'contact_name' => 'Private Contact']);
    app(SaveOccupant::class)->create($enumerator, $household, ['full_name' => 'Hawa Sirleaf', 'age_years' => 35, 'sex' => 'female', 'relationship_to_head' => 'head', 'usual_resident' => true]);
    app(CensusWorkflow::class)->submit($enumerator, $household);

    return [...$chain, 'campaign' => $campaign, 'enumerator' => $enumerator, 'household' => $household->fresh()];
}

test('a national administrator creates a campaign with geographic coverage and starts it', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::NationalCensusAdministrator);

    $this->actingAs($admin)->post(route('admin.census.campaigns.store'), [
        'reference_code' => 'LCC2026',
        'name' => 'Community census 2026',
        'start_date' => '2026-11-01',
        'end_date' => '2026-12-15',
        'reference_date' => '2026-11-01',
        'scopes' => [['level' => 'county', 'area_id' => $chain['county']->id]],
    ])->assertRedirect();

    $campaign = CensusCampaign::query()->where('reference_code', 'LCC2026')->sole();
    expect($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->scopes()->sole()->county_id)->toBe($chain['county']->id);

    $this->actingAs($admin)->patch(route('admin.census.campaigns.status', $campaign), ['status' => 'active'])->assertSessionHasNoErrors();
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Active);
});

test('a campaign without coverage cannot start field work', function () {
    $admin = staffUser(RoleName::NationalCensusAdministrator);
    $campaign = CensusCampaign::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.census.campaigns.status', $campaign), ['status' => 'active'])
        ->assertSessionHasErrors(['status' => 'Define the geographic coverage before starting field work.']);
});

test('county administrators cannot create campaigns', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $chain['county']);

    $this->actingAs($admin)->get(route('admin.census.campaigns.create'))->assertForbidden();
});

test('closing a campaign ends its assignments', function () {
    $s = adminScenario();
    $admin = staffUser(RoleName::NationalCensusAdministrator);
    $s['campaign']->forceFill(['status' => CampaignStatus::InReview])->save();

    $this->actingAs($admin)->patch(route('admin.census.campaigns.status', $s['campaign']), ['status' => 'closed'])->assertSessionHasNoErrors();

    expect($s['campaign']->fresh()->status)->toBe(CampaignStatus::Closed)
        ->and($s['campaign']->assignments()->where('status', 'active')->count())->toBe(0);
});

test('assignments cannot be placed outside the administrator area or the campaign coverage', function () {
    $s = adminScenario();
    $other = geoChain();
    $countyAdmin = staffUser(RoleName::CountyAdministrator, $s['county']);
    $enumerator = staffUser(RoleName::CensusEnumerator, $s['county']);

    $this->actingAs($countyAdmin)->post(route('admin.census.campaigns.assignments.store', $s['campaign']), [
        'user_id' => $enumerator->id,
        'role' => 'enumerator',
        'level' => 'block',
        'area_id' => $other['block']->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => $s['campaign']->end_date->toDateString(),
    ])->assertSessionHasErrors('area_id');

    $this->actingAs($countyAdmin)->post(route('admin.census.campaigns.assignments.store', $s['campaign']), [
        'user_id' => $enumerator->id,
        'role' => 'enumerator',
        'level' => 'block',
        'area_id' => $s['block']->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => $s['campaign']->end_date->toDateString(),
    ])->assertSessionHasNoErrors();

    expect(CampaignAssignment::query()->where('user_id', $enumerator->id)->sole()->block_id)->toBe($s['block']->id);
});

test('staff without a field role cannot be assigned as enumerators', function () {
    $s = adminScenario();
    $admin = staffUser(RoleName::NationalCensusAdministrator);
    $editor = staffUser(RoleName::ContentEditor);

    $this->actingAs($admin)->post(route('admin.census.campaigns.assignments.store', $s['campaign']), [
        'user_id' => $editor->id,
        'role' => 'enumerator',
        'level' => 'block',
        'area_id' => $s['block']->id,
        'starts_on' => now()->toDateString(),
        'ends_on' => $s['campaign']->end_date->toDateString(),
    ])->assertSessionHasErrors('user_id');
});

test('a supervisor verifies through the review endpoint', function () {
    $s = adminScenario();
    $supervisor = staffUser(RoleName::CensusSupervisor, $s['town']);

    $this->actingAs($supervisor)
        ->post(route('admin.census.households.reviews.store', $s['household']), ['action' => 'verify', 'version' => $s['household']->version])
        ->assertSessionHasNoErrors();

    expect($s['household']->fresh()->status)->toBe(CensusRecordStatus::Verified);
});

test('a stale review is refused instead of overwriting newer changes', function () {
    $s = adminScenario();
    $supervisor = staffUser(RoleName::CensusSupervisor, $s['town']);

    $this->actingAs($supervisor)
        ->post(route('admin.census.households.reviews.store', $s['household']), ['action' => 'verify', 'version' => $s['household']->version - 1])
        ->assertSessionHasErrors('version');

    expect($s['household']->fresh()->status)->toBe(CensusRecordStatus::Submitted);
});

test('reviewers in another county cannot see or act on a household', function () {
    $s = adminScenario();
    $other = geoChain();
    $foreignSupervisor = staffUser(RoleName::CensusSupervisor, $other['county']);

    $this->actingAs($foreignSupervisor)->get(route('admin.census.households.show', $s['household']))->assertNotFound();
    $this->actingAs($foreignSupervisor)
        ->post(route('admin.census.households.reviews.store', $s['household']), ['action' => 'verify', 'version' => $s['household']->version])
        ->assertNotFound();

    $this->actingAs($foreignSupervisor)
        ->get(route('admin.census.households.index', ['campaign' => $s['campaign']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('households', null));
});

test('names are hidden from roles without personal-data permission', function () {
    $s = adminScenario();
    $blockLeader = staffUser(RoleName::BlockLeader, $s['block']);
    $countyAdmin = staffUser(RoleName::CountyAdministrator, $s['county']);

    $this->actingAs($blockLeader)
        ->get(route('admin.census.households.show', $s['household']))
        ->assertOk()
        ->assertDontSee('Hawa Sirleaf')
        ->assertDontSee('Private Contact')
        ->assertInertia(fn ($page) => $page->where('occupants.0.full_name', null)->where('can.viewPersonalData', false));

    $this->actingAs($countyAdmin)
        ->get(route('admin.census.households.show', $s['household']))
        ->assertInertia(fn ($page) => $page->where('occupants.0.full_name', 'Hawa Sirleaf'));
});

test('the population report only counts records inside the viewer area', function () {
    $s = adminScenario();
    $admin = staffUser(RoleName::NationalCensusAdministrator);
    $supervisor = staffUser(RoleName::CensusSupervisor, $s['town']);
    app(CensusWorkflow::class)->verify($supervisor, $s['household']);
    app(CensusWorkflow::class)->approve($admin, Household::query()->findOrFail($s['household']->id));

    $outsider = staffUser(RoleName::ReportViewer, geoChain()['county']);

    $this->actingAs($admin)
        ->get(route('admin.census.population', ['campaign' => $s['campaign']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('summary.occupants.approved', 1));

    $this->actingAs($outsider)
        ->get(route('admin.census.population'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('campaign', null));
});
