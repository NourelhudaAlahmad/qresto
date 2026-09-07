<?php

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('switches locale for a guest and persists it', function () {
    $response = $this->post('/locale', [
        'locale' => 'ar',
    ]);

    $response->assertRedirect();
    $response->assertCookie('locale', 'ar');

    expect(session('locale'))->toBe('ar');
});

it('switches locale for an authenticated staff user', function () {
    $restaurant = Restaurant::factory()->create([
        'supported_locales' => ['en', 'ar'],
        'default_locale' => 'en',
    ]);

    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'locale' => 'en',
    ]);

    $response = $this
        ->actingAs($user)
        ->post('/locale', [
            'locale' => 'ar',
        ]);

    $response->assertRedirect();
    $response->assertCookie('locale', 'ar');

    expect($user->fresh()->locale)
        ->toBe('ar')
        ->and(session('locale'))
        ->toBe('ar');
});

it('rejects unsupported locales', function () {
    $response = $this->post('/locale', [
        'locale' => 'fr',
    ]);

    $response->assertSessionHasErrors('locale');
});

it('uses the browser language for a guest', function () {
    $response = $this->withHeader('Accept-Language', 'ar')
        ->get('/');

    $response->assertSuccessful();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('locale', 'ar')
            ->where('dir', 'rtl')
            ->where('available_locales', ['en', 'ar']),
    );
});

it('prefers the saved user locale over the browser language', function () {
    $restaurant = Restaurant::factory()->create([
        'supported_locales' => ['en', 'ar'],
        'default_locale' => 'en',
    ]);

    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'locale' => 'en',
    ]);

    $response = $this
        ->actingAs($user)
        ->withHeader('Accept-Language', 'ar')
        ->get('/');

    $response->assertSuccessful();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('locale', 'en')
            ->where('dir', 'ltr'),
    );
});
