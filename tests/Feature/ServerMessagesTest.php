<?php

use App\Actions\Payments\PromotionValidator;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

/*
 * Messages the server sends to people come from lang/{en,ar}/messages.php,
 * so an Arabic page never shows an English error. These tests keep the two
 * files in step and catch a key used in code but missing from either one.
 */

$messageKeys = fn (string $locale): array => array_keys(Arr::dot(require lang_path("{$locale}/messages.php")));

it('keeps messages.php key-for-key between en and ar', function () use ($messageKeys) {
    $en = $messageKeys('en');
    $ar = $messageKeys('ar');

    expect(array_values(array_diff($en, $ar)))
        ->toBe([], 'Keys in lang/en/messages.php missing from lang/ar/messages.php')
        ->and(array_values(array_diff($ar, $en)))
        ->toBe([], 'Keys in lang/ar/messages.php missing from lang/en/messages.php');
});

it('keeps the same placeholders in both languages', function () {
    $en = Arr::dot(require lang_path('en/messages.php'));
    $ar = Arr::dot(require lang_path('ar/messages.php'));

    $placeholders = function (string $text): array {
        preg_match_all('/:[a-z_]+/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    };

    foreach ($en as $key => $text) {
        expect($placeholders($ar[$key]))->toBe($placeholders($text), "Placeholders differ for messages.{$key}");
    }
});

it('defines every messages key the app uses', function () {
    $used = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        preg_match_all("/__\\('messages\\.([a-z_.]+)'/", $file->getContents(), $matches);

        foreach ($matches[1] as $key) {
            $used[$key] = $file->getRelativePathname();
        }
    }

    expect($used)->not->toBeEmpty();

    foreach ($used as $key => $file) {
        foreach (['en', 'ar'] as $locale) {
            expect(Lang::has("messages.{$key}", $locale, false))
                ->toBeTrue("{$file} uses messages.{$key}, which lang/{$locale}/messages.php does not define");
        }
    }
});

it('translates every built-in Laravel message into Arabic', function () {
    foreach (['auth', 'pagination', 'passwords', 'validation'] as $group) {
        $framework = Arr::except(Arr::dot(trans($group, [], 'en')), ['attributes']);
        $framework = array_filter(
            $framework,
            fn (string $key): bool => ! str_starts_with($key, 'custom.'),
            ARRAY_FILTER_USE_KEY,
        );

        foreach (array_keys($framework) as $key) {
            expect(Lang::has("{$group}.{$key}", 'ar', false))
                ->toBeTrue("lang/ar/{$group}.php is missing {$key}");
        }
    }
});

it('answers an Arabic request with an Arabic validation error', function () {
    // An empty sign-in form fails Laravel's own "required" rule.
    $response = $this
        ->withSession(['locale' => 'ar'])
        ->post('/login', []);

    $response->assertSessionHasErrors('email');

    $message = session('errors')->first('email');

    expect($message)
        ->toBe(trans('validation.required', ['attribute' => trans('validation.attributes.email', [], 'ar')], 'ar'))
        ->not->toContain('required');
});

it('returns app errors in the active language', function () {
    $restaurant = Restaurant::factory()->create();

    app()->setLocale('ar');

    try {
        app(PromotionValidator::class)->validate(
            restaurantId: $restaurant->id,
            code: 'NO-SUCH-CODE',
            subtotal: Money::fromMinor(10000, $restaurant->currency),
        );

        $this->fail('An unknown promo code should be rejected.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['promo_code'][0])
            ->toBe(trans('messages.promo.invalid', [], 'ar'))
            ->not->toBe(trans('messages.promo.invalid', [], 'en'));
    }
});
