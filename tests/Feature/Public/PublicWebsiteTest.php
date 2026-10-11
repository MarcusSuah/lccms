<?php

use App\Actions\Census\CensusWorkflow;
use App\Actions\Census\RegisterHouse;
use App\Actions\Census\SaveHousehold;
use App\Actions\Census\SaveOccupant;
use App\Enums\Access\RoleName;
use App\Enums\Census\CampaignStatus;
use App\Enums\Content\PostStatus;
use App\Enums\Facilities\FacilityCategory;
use App\Enums\VerificationStatus;
use App\Models\Census\CampaignAssignment;
use App\Models\Census\CensusCampaign;
use App\Models\Content\ContactMessage;
use App\Models\Content\Post;
use App\Models\Facilities\Facility;
use App\Models\Leadership\LeadershipTenure;

/**
 * A published community with approved results in a closed campaign.
 *
 * @return array<string, mixed>
 */
function publishedCommunity(): array
{
    $chain = geoChain();
    $chain['community']->update(['is_published' => true]);
    $campaign = CensusCampaign::factory()->active()->covering($chain['county'])->create();
    $enumerator = staffUser(RoleName::CensusEnumerator);
    CampaignAssignment::factory()->for($campaign, 'campaign')->for($enumerator)->inArea($chain['community'])->create();

    $house = app(RegisterHouse::class)->create($enumerator, $campaign, $chain['block'], ['latitude' => 6.31234, 'longitude' => -10.80456]);
    $household = app(SaveHousehold::class)->create($enumerator, $campaign, $house, ['residence_type' => 'usual', 'contact_name' => 'Private Contact Person']);
    app(SaveOccupant::class)->create($enumerator, $household, ['full_name' => 'Secret Head Name', 'age_years' => 50, 'sex' => 'female', 'relationship_to_head' => 'head', 'usual_resident' => true]);
    app(SaveOccupant::class)->create($enumerator, $household, ['full_name' => 'Secret Child Name', 'age_years' => 7, 'sex' => 'male', 'relationship_to_head' => 'child', 'usual_resident' => true]);

    $workflow = app(CensusWorkflow::class);
    $workflow->submit($enumerator, $household);
    $workflow->verify(staffUser(RoleName::CensusSupervisor, $chain['town']), $household->fresh());
    $workflow->approve(staffUser(RoleName::NationalCensusAdministrator), $household->fresh());
    $campaign->forceFill(['status' => CampaignStatus::Closed])->save();
    auth()->logout();

    return [...$chain, 'campaign' => $campaign];
}

test('the home page shows approved totals from the latest closed campaign only', function () {
    publishedCommunity();
    $active = CensusCampaign::factory()->active()->create(['reference_date' => now()->addYear()->toDateString()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('public/home')
            ->where('population.approved_occupants', 2)
            ->where('population.approved_households', 1)
            ->whereNot('population.campaign.reference_code', $active->reference_code));
});

test('the home page explains when no results are published', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('population', null));
});

test('a community profile never exposes individuals, households or house locations', function () {
    $s = publishedCommunity();

    $this->get(route('communities.show', $s['community']->slug))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/communities/show')->where('population.approved_occupants', 2))
        ->assertDontSee('Secret Head Name')
        ->assertDontSee('Secret Child Name')
        ->assertDontSee('Private Contact Person')
        ->assertDontSee('6.31234')
        ->assertDontSee('-10.80456');
});

test('unpublished communities are not public', function () {
    $chain = geoChain();

    $this->get(route('communities.show', $chain['community']->slug))->assertNotFound();
});

test('only verified public facilities and leaders appear, without private contacts', function () {
    $s = publishedCommunity();
    Facility::factory()->inCommunity($s['community'])->ofCategory(FacilityCategory::Clinic)->verified()->create(['name' => 'Verified Clinic', 'is_public' => true, 'contact_phone' => '0770000001']);
    Facility::factory()->inCommunity($s['community'])->ofCategory(FacilityCategory::School)->create(['name' => 'Unverified School', 'is_public' => true]);
    LeadershipTenure::factory()->inCommunity($s['community'])->create(['holder_name' => 'Public Chair', 'is_public' => true, 'verification_status' => VerificationStatus::Verified, 'contact_phone' => '0770000002']);

    $this->get(route('communities.show', $s['community']->slug))
        ->assertSee('Verified Clinic')
        ->assertDontSee('Unverified School')
        ->assertSee('Public Chair')
        ->assertDontSee('0770000001')
        ->assertDontSee('0770000002');
});

test('only live posts are public and their HTML is sanitised', function () {
    $author = staffUser(RoleName::ContentEditor);
    $this->actingAs($author)->post(route('admin.content.posts.store'), [
        'title' => 'Water point repaired',
        'body_markdown' => "## Update\n\nThe pump works.<script>alert('x')</script> [bad](javascript:alert(1)) <img src=x onerror=alert(1)>",
    ])->assertRedirect();

    $post = Post::query()->sole();

    expect($post->body_html)->not->toContain('<script')
        ->and($post->body_html)->not->toContain('javascript:')
        ->and($post->body_html)->not->toContain('<img')
        ->and($post->body_html)->toContain('<h2>Update</h2>');

    auth()->logout();
    $this->get(route('news.show', $post->slug))->assertNotFound();

    $post->forceFill(['status' => PostStatus::Published, 'published_at' => now()->subMinute()])->save();
    $this->get(route('news.show', $post->slug))->assertOk();
});

test('editors cannot publish without the publish permission', function () {
    $editor = staffUser(RoleName::CommunityAdministrator);
    $editor->givePermissionTo('content.manage');
    $this->actingAs($editor)->post(route('admin.content.posts.store'), ['title' => 'Draft', 'body_markdown' => 'Text']);

    $this->actingAs($editor)
        ->patch(route('admin.content.posts.status', Post::query()->sole()), ['action' => 'publish'])
        ->assertForbidden();
});

test('scheduled posts appear only after their publication time', function () {
    $publisher = staffUser(RoleName::ContentEditor);
    $this->actingAs($publisher)->post(route('admin.content.posts.store'), ['title' => 'Future news', 'body_markdown' => 'Soon']);
    $post = Post::query()->sole();

    $this->actingAs($publisher)
        ->patch(route('admin.content.posts.status', $post), ['action' => 'schedule', 'published_at' => now()->addDay()->toDateTimeString()])
        ->assertSessionHasNoErrors();

    auth()->logout();
    $this->get(route('news.show', $post->slug))->assertNotFound();

    $this->travel(2)->days();
    $this->get(route('news.show', $post->slug))->assertOk();
});

test('the contact form stores a message and is rate limited', function () {
    config(['honeypot.enabled' => false]);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('contact.store'), [
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'subject' => 'Question',
            'message' => 'When will results be published?',
        ])->assertSessionHasNoErrors();
    }

    $this->post(route('contact.store'), [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'subject' => 'Question',
        'message' => 'When will results be published?',
    ])->assertTooManyRequests();

    expect(ContactMessage::query()->count())->toBe(5)
        ->and(ContactMessage::query()->first()->ip_hash)->not->toBe('127.0.0.1');
});

test('the honeypot rejects bots that fill the hidden field', function () {
    config(['honeypot.enabled' => true, 'honeypot.randomize_name_field_name' => false]);

    $this->post(route('contact.store'), [
        'my_name' => 'I am a bot',
        'name' => 'Bot',
        'email' => 'bot@example.test',
        'subject' => 'Spam',
        'message' => 'Buy cheap things now please',
    ]);

    expect(ContactMessage::query()->count())->toBe(0);
});
