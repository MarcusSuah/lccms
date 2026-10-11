<?php

use App\Enums\Access\RoleName;
use App\Enums\Complaints\ComplaintStatus;
use App\Models\Complaints\Complaint;
use App\Notifications\ComplaintAssigned;
use App\Notifications\ComplaintUpdated;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['honeypot.enabled' => false]);
});

function complaintPayload(array $chain, array $overrides = []): array
{
    return [
        'subject' => 'Hand pump broken for two weeks',
        'category' => 'water',
        'description' => 'The only hand pump near the market has been broken since the start of the month.',
        'level' => 'community',
        'area_id' => $chain['community']->id,
        'complainant_name' => 'Musu',
        'complainant_email' => 'musu@example.test',
        ...$overrides,
    ];
}

/**
 * @return array{0: Complaint, 1: string}
 */
function submitComplaint($test, array $chain, array $overrides = []): array
{
    $response = $test->post(route('complaints.store'), complaintPayload($chain, $overrides))->assertRedirect(route('complaints.submitted'));
    $url = session('complaint_submission')['tracking_url'];

    return [Complaint::query()->latest('id')->firstOrFail(), basename($url), $response];
}

test('a complaint with up to five photos is stored privately with a random reference', function () {
    $chain = geoChain();
    $images = array_map(fn (int $i) => UploadedFile::fake()->image("photo{$i}.jpg", 800, 600), range(1, 5));

    [$complaint, $token] = submitComplaint($this, $chain, ['images' => $images]);

    expect($complaint->public_reference)->toMatch('/^CMP-\d{2}-[A-Z0-9]{6}$/')
        ->and($complaint->attachments()->count())->toBe(5)
        ->and($complaint->attachments()->pluck('slot')->all())->toBe([1, 2, 3, 4, 5])
        ->and($complaint->tracking_token_hash)->not->toBe($token)
        ->and(strlen($token))->toBe(48)
        ->and($complaint->community_id)->toBe($chain['community']->id)
        ->and($complaint->county_id)->toBe($chain['county']->id);

    foreach ($complaint->attachments as $attachment) {
        Storage::disk('local')->assertExists($attachment->path);
        expect($attachment->path)->toStartWith('complaints/')->not->toContain('photo');
    }
});

test('more than five photos are refused by the server', function () {
    $chain = geoChain();
    $images = array_map(fn (int $i) => UploadedFile::fake()->image("photo{$i}.jpg"), range(1, 6));

    $this->post(route('complaints.store'), complaintPayload($chain, ['images' => $images]))
        ->assertSessionHasErrors(['images' => 'You can attach at most 5 images.']);

    expect(Complaint::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

test('the database itself refuses a sixth attachment', function () {
    $chain = geoChain();
    [$complaint] = submitComplaint($this, $chain);

    expect(fn () => DB::table('complaint_attachments')->insert([
        'complaint_id' => $complaint->id, 'slot' => 6, 'disk' => 'local', 'path' => 'x', 'thumbnail_path' => 'y',
        'mime' => 'image/webp', 'size' => 1, 'width' => 1, 'height' => 1, 'sha256' => str_repeat('a', 64),
    ]))->toThrow(QueryException::class, 'complaint_attachments_max_five');
});

test('malicious or non-image uploads are rejected and nothing is kept', function (string $name, string $content) {
    $chain = geoChain();
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    $this->post(route('complaints.store'), complaintPayload($chain, [
        'images' => [UploadedFile::fake()->image('ok.jpg'), new UploadedFile($path, $name, null, null, true)],
    ]))->assertSessionHasErrors();

    expect(Complaint::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'php disguised as jpg' => ['evil.jpg', '<?php echo shell_exec($_GET["c"]); ?>'],
    'windows executable' => ['evil.png', "MZ\x90\x00\x03\x00\x00\x00".str_repeat("\x00", 64)],
    'path traversal name' => ['../../../../public/evil.php', '<?php phpinfo();'],
]);

test('an anonymous complaint stores no contact details', function () {
    $chain = geoChain();

    [$complaint] = submitComplaint($this, $chain, ['is_anonymous' => true]);

    expect($complaint->is_anonymous)->toBeTrue()
        ->and($complaint->complainant_name)->toBeNull()
        ->and($complaint->complainant_email)->toBeNull();
});

test('complaints are tracked only with the token, never a guessable id', function () {
    $chain = geoChain();
    [$complaint, $token] = submitComplaint($this, $chain);

    $this->get(route('complaints.track', $token))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('public/complaints/track')->where('complaint.reference', $complaint->public_reference))
        ->assertDontSee('musu@example.test');

    $this->get('/complaints/track/'.str_repeat('A', 48))->assertNotFound();
    $this->get('/complaints/track/'.$complaint->id)->assertNotFound();
    $this->get(route('admin.complaints.show', $complaint))->assertRedirect(route('login'));
});

test('complaint officers only see complaints in their area', function () {
    $mine = geoChain();
    $other = geoChain();
    [$visible] = submitComplaint($this, $mine);
    [$hidden] = submitComplaint($this, $other);
    $officer = staffUser(RoleName::ComplaintOfficer, $mine['county']);

    $this->actingAs($officer)->get(route('admin.complaints.index'))
        ->assertInertia(fn ($page) => $page->where('complaints.data', fn ($rows) => collect($rows)->pluck('reference')->all() === [$visible->public_reference]));

    $this->actingAs($officer)->get(route('admin.complaints.show', $hidden))->assertNotFound();
    $this->actingAs($officer)->get(route('admin.complaints.attachments.show', ['complaint' => $hidden->public_reference, 'slot' => 1]))->assertNotFound();
});

test('officers assign, respond and resolve, and the complainant is notified', function () {
    Notification::fake();
    $chain = geoChain();
    [$complaint, $token] = submitComplaint($this, $chain);
    $lead = staffUser(RoleName::ComplaintOfficer, $chain['county']);
    $officer = staffUser(RoleName::ComplaintOfficer, $chain['community']);

    $this->actingAs($lead)->patch(route('admin.complaints.status', $complaint), ['status' => 'received'])->assertSessionHasNoErrors();
    $this->actingAs($lead)->patch(route('admin.complaints.assign', $complaint), ['assigned_to' => $officer->id, 'priority' => 'high'])->assertSessionHasNoErrors();
    Notification::assertSentTo($officer, ComplaintAssigned::class);

    $this->actingAs($officer)->post(route('admin.complaints.notes.store', $complaint), ['body' => 'Called the water committee.'])->assertSessionHasNoErrors();
    $this->actingAs($officer)->patch(route('admin.complaints.status', $complaint->fresh()), ['status' => 'resolved', 'public_response' => 'The pump was repaired today.'])
        ->assertSessionHasErrors('resolution_summary');
    $this->actingAs($officer)->patch(route('admin.complaints.status', $complaint->fresh()), [
        'status' => 'resolved',
        'public_response' => 'The pump was repaired today.',
        'resolution_summary' => 'Seal replaced by the county WASH team.',
    ])->assertSessionHasNoErrors();

    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Resolved)
        ->and($complaint->fresh()->assigned_to)->toBe($officer->id);

    Notification::assertSentOnDemand(ComplaintUpdated::class);

    auth()->logout();
    $this->get(route('complaints.track', $token))
        ->assertSee('The pump was repaired today.')
        ->assertDontSee('Called the water committee.');
});

test('rejecting a complaint requires a reason for the complainant', function () {
    $chain = geoChain();
    [$complaint] = submitComplaint($this, $chain);
    $officer = staffUser(RoleName::ComplaintOfficer, $chain['county']);

    $this->actingAs($officer)->patch(route('admin.complaints.status', $complaint), ['status' => 'rejected'])
        ->assertSessionHasErrors('public_response');
});

test('invalid status jumps are refused', function () {
    $chain = geoChain();
    [$complaint] = submitComplaint($this, $chain);
    $officer = staffUser(RoleName::ComplaintOfficer, $chain['county']);

    $this->actingAs($officer)->patch(route('admin.complaints.status', $complaint), ['status' => 'closed'])
        ->assertSessionHasErrors('status');
});
