<?php

use App\Enums\Access\RoleName;
use App\Models\Census\CensusCampaign;
use App\Models\Complaints\Complaint;
use App\Models\User;
use Illuminate\Support\Str;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows population cards only for campaigns inside the user area', function () {
    $mine = geoChain();
    $other = geoChain();
    $visible = CensusCampaign::factory()->active()->covering($mine['county'])->create();
    CensusCampaign::factory()->active()->covering($other['county'])->create();
    $admin = staffUser(RoleName::CountyAdministrator, $mine['county']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('campaign.id', $visible->id)
            ->has('campaigns', 1)
            ->has('summary.adults')
            ->has('summary.children')
            ->has('summary.sex'));
});

test('complaint officers see complaint counts for their own areas only', function () {
    $mine = geoChain();
    $other = geoChain();
    $complaint = fn (array $chain, string $status, string $priority = 'normal') => Complaint::query()->forceCreate([
        ...$chain['community']->pathAttributes(),
        'public_reference' => 'CMP-26-'.Str::upper(Str::random(6)),
        'tracking_token_hash' => hash('sha256', Str::random(48)),
        'subject' => 'Broken hand pump',
        'category' => 'water',
        'description' => 'The hand pump near the market is broken.',
        'status' => $status,
        'priority' => $priority,
    ]);
    $complaint($mine, 'submitted', 'urgent');
    $complaint($mine, 'in_progress');
    $complaint($mine, 'resolved');
    $complaint($other, 'submitted', 'urgent');
    $officer = staffUser(RoleName::ComplaintOfficer, $mine['county']);

    $this->actingAs($officer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary', null)
            ->where('complaints.open', 2)
            ->where('complaints.urgent_open', 1)
            ->where('complaints.by_status', fn ($statuses) => collect($statuses)->firstWhere('status', 'resolved')['total'] === 1));
});
