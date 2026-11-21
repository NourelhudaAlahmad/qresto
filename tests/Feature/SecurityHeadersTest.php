<?php

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hardens every page against framing, sniffing and leaky referrers', function (string $path) {
    Restaurant::factory()->create(['slug' => 'al-bustan']);

    $this->get($path)
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), microphone=(), geolocation=()');
})->with([
    'the landing page' => '/',
    'staff sign-in' => '/login',
    'a missing page' => '/no-such-page',
]);

it('keeps HSTS off outside production, where the site may not have HTTPS', function () {
    $this->get('https://qresto.test/login')->assertHeaderMissing('Strict-Transport-Security');
});

it('tells browsers to stay on HTTPS in production', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config(['qresto.deploy.enforce' => false]);

    $this->get('https://qresto.test/login')
        ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
});
