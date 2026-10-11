<?php

use App\Enums\Access\RoleName;
use App\Enums\Facilities\FacilityCategory;
use App\Enums\Facilities\OperatingStatus;
use App\Enums\Facilities\Ownership;
use App\Models\Facilities\Facility;
use App\Models\Facilities\FacilityType;
use App\Services\Facilities\NeedsAssessment;
use Database\Seeders\ReferenceDataSeeder;

test('a facility takes its reporting category from its type and gets a community reference', function () {
    $this->seed(ReferenceDataSeeder::class);
    $chain = geoChain();
    $inspector = staffUser(RoleName::FacilityInspector, $chain['county']);
    $type = FacilityType::query()->where('code', 'clinic')->sole();

    $this->actingAs($inspector)->post(route('admin.facilities.store'), [
        'name' => 'Riverside Clinic',
        'facility_type_id' => $type->id,
        'ownership' => 'private',
        'operating_status' => 'operational',
        'community_id' => $chain['community']->id,
        'block_id' => $chain['block']->id,
        'verification_status' => 'unverified',
        'status' => 'active',
        'details' => ['services' => ['outpatient', 'maternity'], 'beds' => 12, 'enrolment_total' => 400],
    ])->assertRedirect();

    $facility = Facility::query()->sole();

    expect($facility->category)->toBe(FacilityCategory::Clinic)
        ->and($facility->reference_code)->toBe($chain['community']->reference_code.'-F001')
        ->and($facility->block_id)->toBe($chain['block']->id)
        ->and($facility->details)->toEqual(['services' => ['outpatient', 'maternity'], 'beds' => 12]);
});

test('category specific details are validated', function () {
    $this->seed(ReferenceDataSeeder::class);
    $chain = geoChain();
    $inspector = staffUser(RoleName::FacilityInspector, $chain['county']);

    $this->actingAs($inspector)->post(route('admin.facilities.store'), [
        'name' => 'Hilltop School',
        'facility_type_id' => FacilityType::query()->where('code', 'primary_school')->value('id'),
        'ownership' => 'public',
        'operating_status' => 'operational',
        'community_id' => $chain['community']->id,
        'verification_status' => 'unverified',
        'status' => 'active',
        'details' => ['enrolment_total' => 100, 'enrolment_female' => 150, 'education_levels' => ['university']],
    ])->assertSessionHasErrors(['details.enrolment_female', 'details.education_levels.0']);
});

test('a block from another community is rejected', function () {
    $this->seed(ReferenceDataSeeder::class);
    $chain = geoChain();
    $foreignBlock = geoChain()['block'];
    $admin = staffUser(RoleName::NationalCensusAdministrator);

    $this->actingAs($admin)->post(route('admin.facilities.store'), [
        'name' => 'Mismatched Market',
        'facility_type_id' => FacilityType::query()->where('code', 'market')->value('id'),
        'ownership' => 'community',
        'operating_status' => 'operational',
        'community_id' => $chain['community']->id,
        'block_id' => $foreignBlock->id,
        'verification_status' => 'unverified',
        'status' => 'active',
    ])->assertSessionHasErrors(['block_id' => 'The block is not in the selected community.']);
});

test('inspectors without verification rights cannot mark facilities verified', function () {
    $chain = geoChain();
    $inspector = staffUser(RoleName::FacilityInspector, $chain['county']);
    $facility = Facility::factory()->inCommunity($chain['community'])->create();

    $this->actingAs($inspector)->put(route('admin.facilities.update', $facility), [
        'name' => $facility->name,
        'facility_type_id' => $facility->facility_type_id,
        'ownership' => 'public',
        'operating_status' => 'operational',
        'community_id' => $chain['community']->id,
        'verification_status' => 'verified',
        'status' => 'active',
    ])->assertForbidden();
});

test('facilities outside the user area are hidden and protected', function () {
    $mine = geoChain();
    $other = geoChain();
    $inspector = staffUser(RoleName::FacilityInspector, $mine['county']);
    $visible = Facility::factory()->inCommunity($mine['community'])->create();
    $hidden = Facility::factory()->inCommunity($other['community'])->create();

    $this->actingAs($inspector)->get(route('admin.facilities.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('facilities.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$visible->id]));

    $this->actingAs($inspector)->get(route('admin.facilities.show', $hidden))->assertNotFound();
});

test('an inspection is recorded and can update the operating status', function () {
    $chain = geoChain();
    $inspector = staffUser(RoleName::FacilityInspector, $chain['county']);
    $facility = Facility::factory()->inCommunity($chain['community'])->create();

    $this->actingAs($inspector)->post(route('admin.facilities.inspections.store', $facility), [
        'inspected_on' => now()->toDateString(),
        'operating_status' => 'closed_down',
        'condition' => 'poor',
        'findings' => 'Roof collapsed; classes suspended.',
        'update_status' => true,
    ])->assertSessionHasNoErrors();

    expect($facility->fresh()->operating_status)->toBe(OperatingStatus::Closed)
        ->and($facility->inspections()->sole()->inspector_id)->toBe($inspector->id);
});

test('facility counts give one count per facility by category and ownership', function () {
    $chain = geoChain();
    Facility::factory()->count(2)->inCommunity($chain['community'])->ofCategory(FacilityCategory::School)->create(['ownership' => Ownership::Public]);
    Facility::factory()->inCommunity($chain['community'])->ofCategory(FacilityCategory::School)->create(['ownership' => Ownership::Private]);
    Facility::factory()->inCommunity($chain['community'])->ofCategory(FacilityCategory::Clinic)->create(['ownership' => Ownership::Private]);

    $counts = app(NeedsAssessment::class)->facilityCounts(null, ['community_id' => $chain['community']->id]);

    expect($counts['school'])->toEqual(['private' => 1, 'public' => 2])
        ->and($counts['clinic'])->toBe(['private' => 1]);
});

test('the needs assessment distinguishes verified absence, gaps and unknowns', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::NationalCensusAdministrator);
    Facility::factory()->inCommunity($chain['community'])->ofCategory(FacilityCategory::School)->verified()->create();
    Facility::factory()->inCommunity($chain['community'])->ofCategory(FacilityCategory::Market)->verified()->create(['operating_status' => OperatingStatus::Closed]);

    $this->actingAs($admin)->put(route('admin.needs-assessment.update', $chain['community']), [
        'category' => 'public_toilet',
        'status' => 'verified_absent',
        'assessed_on' => now()->toDateString(),
        'notes' => 'Walked the whole community.',
    ])->assertSessionHasNoErrors();

    $row = app(NeedsAssessment::class)->matrix([$chain['community']->id])[$chain['community']->id];

    expect($row['school'])->toBe('present')
        ->and($row['market'])->toBe('gap')
        ->and($row['public_toilet'])->toBe('verified_absent')
        ->and($row['health_facility'])->toBe('not_assessed');
});
