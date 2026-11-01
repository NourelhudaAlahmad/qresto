<?php

use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
 * Shared copy (lang/{en,ar}/guest.php and orders.php) reaches every page as
 * `i18n`, for components such as StatusPill, HelpPanel and the guest tab bar.
 * resources/js/i18n/en.json is the English fallback the browser bundles for
 * component tests, so it must match lang/en exactly.
 */

$groups = ['guest', 'orders'];

it('keeps resources/js/i18n/en.json identical to lang/en', function () use ($groups) {
    $json = json_decode(
        (string) file_get_contents(resource_path('js/i18n/en.json')),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $expected = [];

    foreach ($groups as $group) {
        $expected[$group] = trans($group, [], 'en');
    }

    expect($json)->toBe($expected);
});

it('keeps the shared files key-for-key, translated, with matching placeholders', function (string $group) {
    $en = Arr::dot(trans($group, [], 'en'));
    $ar = Arr::dot(trans($group, [], 'ar'));

    expect(array_keys($ar))->toBe(array_keys($en));

    $placeholders = function (string $text): array {
        preg_match_all('/:[a-z_]+/', $text, $matches);
        sort($matches[0]);

        return $matches[0];
    };

    foreach ($en as $key => $text) {
        expect($ar[$key])
            ->not->toBe($text, "{$group}.{$key} is still English in ar")
            ->and($placeholders($ar[$key]))
            ->toBe($placeholders($text), "Placeholders differ for {$group}.{$key}");
    }
})->with($groups);

it('shares the copy with every page in the active language', function (string $locale) {
    Restaurant::factory()->create([
        'slug' => 'al-bustan',
        'supported_locales' => ['en', 'ar'],
        'default_locale' => 'en',
    ]);

    $this
        ->withSession(['locale' => $locale])
        ->get('/')
        ->assertSuccessful()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('i18n.guest', trans('guest', [], $locale))
                ->where('i18n.orders', trans('orders', [], $locale)),
        );
})->with(['en', 'ar']);
