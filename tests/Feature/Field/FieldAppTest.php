<?php

use App\Actions\Sync\RegisterDevice;
use App\Enums\Access\RoleName;
use App\Models\Sync\Device;

test('enumerators open the field application shell', function () {
    $enumerator = staffUser(RoleName::CensusEnumerator);

    $this->actingAs($enumerator)
        ->get(route('field'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('field/index'));
});

test('staff without field work cannot open the field application', function () {
    $editor = staffUser(RoleName::ContentEditor);

    $this->actingAs($editor)->get(route('field'))->assertForbidden();
});

test('the service worker is served from the root and caches only the field shell', function () {
    $response = $this->get(route('field.service-worker'))
        ->assertOk()
        ->assertHeader('Service-Worker-Allowed', '/');

    expect($response->headers->get('Content-Type'))->toContain('javascript')
        ->and($response->getContent())->toContain("const SHELL = '/field';")
        ->and($response->getContent())->not->toContain('/admin');
});

test('a supervisor revokes an enumerator device in their area', function () {
    $chain = geoChain();
    $enumerator = staffUser(RoleName::CensusEnumerator, $chain['community']);
    $supervisor = staffUser(RoleName::CensusSupervisor, $chain['town']);
    ['device' => $device, 'token' => $token] = app(RegisterDevice::class)->handle($enumerator, 'Tablet', 'test');

    $this->actingAs($supervisor)
        ->delete(route('admin.sync.devices.destroy', $device), ['reason' => 'Reported lost'])
        ->assertSessionHasNoErrors();

    expect($device->fresh()->revoked_at)->not->toBeNull()
        ->and($enumerator->tokens()->count())->toBe(0);
});

test('devices outside the supervisor area cannot be revoked', function () {
    $mine = geoChain();
    $other = geoChain();
    $enumerator = staffUser(RoleName::CensusEnumerator, $other['community']);
    $supervisor = staffUser(RoleName::CensusSupervisor, $mine['town']);
    ['device' => $device] = app(RegisterDevice::class)->handle($enumerator, 'Tablet', 'test');

    $this->actingAs($supervisor)
        ->delete(route('admin.sync.devices.destroy', $device), ['reason' => 'Reported lost'])
        ->assertNotFound();

    expect(Device::query()->findOrFail($device->id)->revoked_at)->toBeNull();
});

test('enumerators cannot register more devices than allowed', function () {
    config(['lccms.offline.max_devices_per_user' => 1]);
    $enumerator = staffUser(RoleName::CensusEnumerator);
    app(RegisterDevice::class)->handle($enumerator, 'First', 'test');

    $this->actingAs($enumerator)
        ->postJson(route('field.devices.store'), ['label' => 'Second'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('device');
});
