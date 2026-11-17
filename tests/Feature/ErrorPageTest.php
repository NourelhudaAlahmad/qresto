<?php

use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
    config(['app.debug' => false]);
});

it('shows the designed 404 page with a way back to the start for guests', function () {
    $this->get('/no-such-page')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('errors/show')
            ->where('status', 404)
            ->where('home', '/')
            ->where('homeLabel', 'Back to the start'));
});

it('sends a waiter back to their own screen from a 403', function () {
    $waiter = User::factory()->create(['restaurant_id' => Restaurant::factory()->create()->id]);
    $waiter->assignRole('waiter');

    $this->actingAs($waiter)
        ->get('/app/system')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->component('errors/show')
            ->where('status', 403)
            ->where('home', '/app/my-orders')
            ->where('message', 'Your account doesn’t have access to this screen. If you think it should, ask your manager.'));
});

it('shows a message we wrote on purpose, but not the framework default', function () {
    Route::get('/_forbidden-with-reason', fn () => abort(403, 'This table is not yours tonight.'))
        ->middleware('web');

    $this->get('/_forbidden-with-reason')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page->where('message', 'This table is not yours tonight.'));
});

it('keeps JSON errors for JSON requests', function () {
    $this->getJson('/no-such-page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});

it('keeps the stack trace for a 500 while debugging locally', function () {
    config(['app.debug' => true]);

    Route::get('/_boom', fn () => throw new RuntimeException('boom'))
        ->middleware('web');

    $response = $this->get('/_boom')->assertStatus(500);

    expect($response->headers->get('X-Inertia'))->toBeNull();
});

it('shows the designed 500 page in production', function () {
    Route::get('/_boom', fn () => throw new RuntimeException('boom'))
        ->middleware('web');

    $this->get('/_boom')
        ->assertStatus(500)
        ->assertInertia(fn ($page) => $page->component('errors/show')->where('status', 500));
});

it('keeps Retry-After and the rate-limit headers on the designed 429 page', function () {
    Route::get('/_limited', fn () => 'ok')
        ->middleware(['web', 'throttle:1,1']);

    $this->get('/_limited')->assertOk();

    $this->get('/_limited')
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertHeader('X-RateLimit-Limit', '1')
        ->assertInertia(fn ($page) => $page->component('errors/show')->where('status', 429));
});

it('speaks Arabic to an Arabic browser on an unknown URL', function () {
    $this->withHeader('Accept-Language', 'ar')
        ->get('/no-such-page')
        ->assertNotFound()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertInertia(fn ($page) => $page
            ->where('title', 'لم نجد هذه الصفحة')
            ->where('message', 'ربما نُقلت الصفحة، أو أن الرابط قديم.')
            ->where('homeLabel', 'العودة إلى البداية')
            ->where('backLabel', 'رجوع'));
});

it('follows a signed-in waiter\'s chosen language on a 403', function () {
    $waiter = User::factory()->create([
        'restaurant_id' => Restaurant::factory()->create()->id,
        'locale' => 'ar',
    ]);
    $waiter->assignRole('waiter');

    $this->actingAs($waiter)
        ->get('/app/system')
        ->assertForbidden()
        ->assertInertia(fn ($page) => $page
            ->where('title', 'هذه الصفحة غير متاحة لك')
            ->where('homeLabel', 'العودة إلى شاشتك'));
});
