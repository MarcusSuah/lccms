<?php

use App\Enums\Access\RoleName;
use App\Models\Leadership\LeadershipPosition;
use App\Models\Leadership\LeadershipTenure;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function tenurePayload(array $chain, LeadershipPosition $position, array $overrides = []): array
{
    return [
        'position_id' => $position->id,
        'community_id' => $chain['community']->id,
        'holder_name' => 'Hon. Joseph Sackie',
        'starts_on' => '2024-01-01',
        'selection_method' => 'elected',
        'verification_status' => 'unverified',
        'is_public' => true,
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->seed(ReferenceDataSeeder::class);
});

test('a new leader closes the predecessor tenure and history is kept', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::CommunityAdministrator, $chain['community']);
    $chair = LeadershipPosition::query()->where('code', 'community_chairperson')->sole();

    $this->actingAs($admin)->post(route('admin.leadership.store'), tenurePayload($chain, $chair))->assertSessionHasNoErrors();
    $this->actingAs($admin)->post(route('admin.leadership.store'), tenurePayload($chain, $chair, [
        'holder_name' => 'Mrs. Comfort Weah',
        'starts_on' => '2026-03-01',
        'close_previous' => true,
    ]))->assertSessionHasNoErrors();

    $tenures = LeadershipTenure::query()->orderBy('starts_on')->get();

    expect($tenures)->toHaveCount(2)
        ->and($tenures[0]->ends_on->toDateString())->toBe('2026-02-28')
        ->and($tenures[1]->ends_on)->toBeNull();
});

test('overlapping tenures for a single-holder position are refused without an exception', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::CommunityAdministrator, $chain['community']);
    $chair = LeadershipPosition::query()->where('code', 'community_chairperson')->sole();

    $this->actingAs($admin)->post(route('admin.leadership.store'), tenurePayload($chain, $chair, ['ends_on' => '2025-12-31']));
    $this->actingAs($admin)->post(route('admin.leadership.store'), tenurePayload($chain, $chair, [
        'holder_name' => 'Rival Claimant',
        'starts_on' => '2025-06-01',
        'ends_on' => '2026-06-01',
    ]))->assertSessionHasErrors('starts_on');

    expect(LeadershipTenure::query()->count())->toBe(1);
});

test('only leadership verifiers may authorise an overlap exception', function () {
    $chain = geoChain();
    $communityAdmin = staffUser(RoleName::CommunityAdministrator, $chain['community']);
    $countyAdmin = staffUser(RoleName::CountyAdministrator, $chain['county']);
    $chair = LeadershipPosition::query()->where('code', 'community_chairperson')->sole();
    $this->actingAs($communityAdmin)->post(route('admin.leadership.store'), tenurePayload($chain, $chair));

    $coChair = tenurePayload($chain, $chair, ['holder_name' => 'Co-chair', 'starts_on' => '2025-01-01', 'exception_note' => 'Co-chairs agreed by community assembly minute 12/2024']);

    $this->actingAs($communityAdmin)->post(route('admin.leadership.store'), $coChair)->assertSessionHasErrors('exception_note');
    $this->actingAs($countyAdmin)->post(route('admin.leadership.store'), $coChair)->assertSessionHasNoErrors();

    expect(LeadershipTenure::query()->whereNull('ends_on')->count())->toBe(2)
        ->and(LeadershipTenure::query()->where('holder_name', 'Co-chair')->value('exception_approved_by'))->toBe($countyAdmin->id);
});

test('block positions require a block', function () {
    $chain = geoChain();
    $admin = staffUser(RoleName::CommunityAdministrator, $chain['community']);
    $blockLeader = LeadershipPosition::query()->where('code', 'block_leader')->sole();

    $this->actingAs($admin)->post(route('admin.leadership.store'), tenurePayload($chain, $blockLeader))->assertSessionHasErrors('block_id');

    $this->actingAs($admin)->post(route('admin.leadership.store'), tenurePayload($chain, $blockLeader, ['block_id' => $chain['block']->id]))->assertSessionHasNoErrors();

    expect(LeadershipTenure::query()->sole()->block_id)->toBe($chain['block']->id);
});

test('supporting documents are stored privately and served only to authorised staff', function () {
    Storage::fake('local');
    $chain = geoChain();
    $other = geoChain();
    $admin = staffUser(RoleName::CommunityAdministrator, $chain['community']);
    $outsider = staffUser(RoleName::CommunityAdministrator, $other['community']);
    $chair = LeadershipPosition::query()->where('code', 'community_chairperson')->sole();

    $this->actingAs($admin)->post(route('admin.leadership.store'), tenurePayload($chain, $chair, [
        'document' => UploadedFile::fake()->create('election-minutes.pdf', 120, 'application/pdf'),
    ]))->assertSessionHasNoErrors();

    $tenure = LeadershipTenure::query()->sole();
    Storage::disk('local')->assertExists($tenure->document_path);
    expect($tenure->document_path)->not->toContain('election-minutes');

    $this->actingAs($admin)->get(route('admin.leadership.document', $tenure))->assertOk();
    $this->actingAs($outsider)->get(route('admin.leadership.document', $tenure))->assertNotFound();
});

test('the database allows only one open tenure per single-holder position and area', function () {
    $chain = geoChain();
    LeadershipTenure::factory()->inCommunity($chain['community'])->create();

    expect(fn () => LeadershipTenure::factory()->inCommunity($chain['community'])->create())
        ->toThrow(UniqueConstraintViolationException::class);
});
