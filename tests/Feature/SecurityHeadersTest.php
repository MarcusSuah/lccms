<?php

use App\Enums\Access\RoleName;

test('public pages send baseline security headers', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=(), payment=(), usb=()')
        ->assertHeaderMissing('Strict-Transport-Security');
});

test('authenticated pages are never stored by caches', function () {
    $admin = staffUser(RoleName::CountyAdministrator, geoChain()['county']);

    $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

test('HSTS is sent over HTTPS', function () {
    $this->get('https://localhost/')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});
