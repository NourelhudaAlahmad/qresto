<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
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
        'slug' => 'al-bustan',
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

it('keeps the locale chosen in staff settings on the next request', function () {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'supported_locales' => ['en', 'ar'],
        'default_locale' => 'en',
    ]);

    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'locale' => 'en',
    ]);

    $this
        ->actingAs($user)
        ->from('/settings/profile')
        ->post('/locale', ['locale' => 'ar'])
        ->assertRedirect('/settings/profile')
        ->assertCookie('locale', 'ar');

    expect($user->fresh()->locale)->toBe('ar');

    $this
        ->actingAs($user->fresh())
        ->get('/settings/profile')
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('locale', 'ar')
                ->where('dir', 'rtl'),
        );
});

it('rejects unsupported locales', function () {
    $response = $this->post('/locale', [
        'locale' => 'fr',
    ]);

    $response->assertSessionHasErrors('locale');
});

it('uses the browser language for a guest', function () {
    Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'supported_locales' => ['en', 'ar'],
        'default_locale' => 'en',
    ]);

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
        'slug' => 'al-bustan',
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

it('translates every payment method and status', function (string $locale) {
    foreach (PaymentMethod::cases() as $method) {
        $key = "payments.methods.{$method->value}";

        expect(Lang::has($key, $locale, false))->toBeTrue("Missing {$key} in lang/{$locale}");
    }

    foreach (PaymentStatus::cases() as $status) {
        $key = "payments.statuses.{$status->value}";

        expect(Lang::has($key, $locale, false))->toBeTrue("Missing {$key} in lang/{$locale}");
    }
})->with(['en', 'ar']);

it('keeps payments.php key-for-key between en and ar', function () {
    $en = array_keys(Arr::dot(require lang_path('en/payments.php')));
    $ar = array_keys(Arr::dot(require lang_path('ar/payments.php')));

    expect(array_values(array_diff($en, $ar)))
        ->toBe([], 'Keys in lang/en/payments.php missing from lang/ar/payments.php')
        ->and(array_values(array_diff($ar, $en)))
        ->toBe([], 'Keys in lang/ar/payments.php missing from lang/en/payments.php');
});

it('renders payment labels in the active locale', function () {
    app()->setLocale('ar');

    expect(PaymentMethod::CARD->label())->toBe('بطاقة')
        ->and(PaymentStatus::PAID->label())->toBe('مدفوع');

    app()->setLocale('en');

    expect(PaymentMethod::CARD->label())->toBe('Card')
        ->and(PaymentStatus::PAID->label())->toBe('Paid');
});

it('labels the language setting from ui.php', function () {
    $restaurant = Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'supported_locales' => ['en', 'ar'],
        'default_locale' => 'en',
    ]);

    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'locale' => 'ar',
    ]);

    $arabic = trans('ui.language', [], 'ar');

    expect($arabic)
        ->not->toBe('ui.language')
        ->not->toBe(trans('ui.language', [], 'en'));

    $this
        ->actingAs($user)
        ->get('/settings/profile')
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page->where('translations.language', $arabic),
        );
});