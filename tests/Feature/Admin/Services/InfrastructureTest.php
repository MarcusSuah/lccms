<?php

use App\Enums\Access\RoleName;
use App\Models\Infrastructure\DevelopmentProject;
use App\Models\Infrastructure\InfrastructureAsset;

test('an infrastructure asset is recorded in a community with a generated reference', function () {
    $chain = geoChain();
    $inspector = staffUser(RoleName::FacilityInspector, $chain['county']);

    $this->actingAs($inspector)->post(route('admin.infrastructure.store'), [
        'name' => 'Hand pump near the school',
        'category' => 'water',
        'community_id' => $chain['community']->id,
        'condition' => 'poor',
        'availability' => 'limited',
        'maintenance_needs' => 'Replace the cylinder seal.',
        'verification_status' => 'unverified',
        'status' => 'active',
    ])->assertRedirect(route('admin.infrastructure.index'));

    expect(InfrastructureAsset::query()->sole()->reference_code)->toBe($chain['community']->reference_code.'-I001');
});

test('a project can sit at district level and is hidden from other counties', function () {
    $chain = geoChain();
    $other = geoChain();
    $admin = staffUser(RoleName::CountyAdministrator, $chain['county']);
    $outsider = staffUser(RoleName::CountyAdministrator, $other['county']);

    $this->actingAs($admin)->post(route('admin.projects.store'), [
        'title' => 'District feeder road rehabilitation',
        'category' => 'roads',
        'status' => 'in_progress',
        'level' => 'district',
        'area_id' => $chain['district']->id,
        'budget_amount' => 120000,
        'budget_currency' => 'USD',
        'verification_status' => 'unverified',
    ])->assertRedirect();

    $project = DevelopmentProject::query()->sole();

    expect($project->level()->value)->toBe('district')
        ->and($project->community_id)->toBeNull()
        ->and($project->reference_code)->toStartWith('PRJ-');

    $this->actingAs($admin)->post(route('admin.projects.reports.store', $project), [
        'reported_on' => now()->toDateString(),
        'progress_percent' => 40,
        'summary' => 'Grading complete on the first 3 km.',
    ])->assertSessionHasNoErrors();

    expect($project->fresh()->progress_percent)->toBe(40);

    $this->actingAs($outsider)->get(route('admin.projects.show', $project))->assertNotFound();
});
