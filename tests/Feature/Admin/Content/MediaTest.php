<?php

use App\Enums\Access\RoleName;
use App\Enums\Content\MediaApproval;
use App\Models\Content\MediaItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function uploadPhoto($test, $user, int $communityId, array $overrides = [])
{
    return $test->actingAs($user)->post(route('admin.content.media.store'), [
        'owner_type' => 'community',
        'owner_id' => $communityId,
        'image' => UploadedFile::fake()->image('market-day.jpg', 1200, 800),
        'caption' => 'Market day',
        'visibility' => 'public',
        'consent_status' => 'not_required',
        ...$overrides,
    ]);
}

test('uploaded photos are re-encoded, renamed, stored privately and pending', function () {
    $chain = geoChain();
    $editor = staffUser(RoleName::ContentEditor, $chain['county']);

    uploadPhoto($this, $editor, $chain['community']->id)->assertSessionHasNoErrors();

    $item = MediaItem::query()->sole();

    expect($item->approval_status)->toBe(MediaApproval::Pending)
        ->and($item->mime)->toBe('image/webp')
        ->and($item->path)->not->toContain('market-day')
        ->and($item->width)->toBeLessThanOrEqual(2400);

    Storage::disk('local')->assertExists([$item->path, $item->thumbnail_path]);
});

test('pending photos are not served to the public', function () {
    $chain = geoChain();
    $editor = staffUser(RoleName::ContentEditor, $chain['county']);
    uploadPhoto($this, $editor, $chain['community']->id);
    $item = MediaItem::query()->sole();

    auth()->logout();
    $this->get(route('media.show', ['media' => $item->uuid, 'variant' => 'thumb']))->assertNotFound();

    $item->forceFill(['approval_status' => MediaApproval::Approved])->save();
    $this->get(route('media.show', ['media' => $item->uuid, 'variant' => 'thumb']))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

test('photos of children cannot be published without recorded consent', function () {
    $chain = geoChain();
    $editor = staffUser(RoleName::ContentEditor, $chain['county']);
    uploadPhoto($this, $editor, $chain['community']->id, ['shows_children' => true, 'consent_status' => 'pending']);
    $item = MediaItem::query()->sole();

    $this->actingAs($editor)
        ->patch(route('admin.content.media.approve', $item->uuid), ['decision' => 'approved'])
        ->assertSessionHasErrors('decision');

    expect($item->fresh()->approval_status)->toBe(MediaApproval::Pending);
});

test('files that are not real images are refused', function (string $name, string $content) {
    $chain = geoChain();
    $editor = staffUser(RoleName::ContentEditor, $chain['county']);
    $path = tempnam(sys_get_temp_dir(), 'upload');
    file_put_contents($path, $content);

    $this->actingAs($editor)->post(route('admin.content.media.store'), [
        'owner_type' => 'community',
        'owner_id' => $chain['community']->id,
        'image' => new UploadedFile($path, $name, null, null, true),
        'visibility' => 'public',
        'consent_status' => 'not_required',
    ])->assertSessionHasErrors('image');

    expect(MediaItem::query()->count())->toBe(0);
})->with([
    'php script named as jpeg' => ['photo.jpg', '<?php system($_GET["c"]); ?>'],
    'html named as png' => ['photo.png', '<html><script>alert(1)</script></html>'],
    'svg with script' => ['photo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
]);

test('staff cannot attach photos to communities outside their area', function () {
    $mine = geoChain();
    $other = geoChain();
    $editor = staffUser(RoleName::ContentEditor, $mine['county']);

    uploadPhoto($this, $editor, $other['community']->id)->assertNotFound();
});
