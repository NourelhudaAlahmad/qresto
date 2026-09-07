<?php

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns the current locale translation for a menu category', function () {
    $restaurant = Restaurant::factory()->create();

    $category = MenuCategory::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Starters',
        'translations' => [
            'en' => 'Starters',
            'ar' => 'المقبلات',
        ],
    ]);

    app()->setLocale('ar');

    expect($category->translatedName)->toBe('المقبلات');

    app()->setLocale('en');

    expect($category->translatedName)->toBe('Starters');
});

it('falls back to English when the current menu category translation is missing', function () {
    $restaurant = Restaurant::factory()->create();

    $category = MenuCategory::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Starters',
        'translations' => [
            'en' => 'Starters',
        ],
    ]);

    app()->setLocale('ar');

    expect($category->translatedName)->toBe('Starters');
});

it('returns the current locale translation for a menu item', function () {
    $restaurant = Restaurant::factory()->create();

    $category = MenuCategory::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Grilled Chicken',
        'description' => 'Grilled chicken with herbs',
        'translations' => [
            'en' => 'Grilled Chicken',
            'ar' => 'دجاج مشوي',
        ],
    ]);

    app()->setLocale('ar');

    expect($item->translatedName)->toBe('دجاج مشوي');
});

it('falls back to English for a menu item when Arabic translation is missing', function () {
    $restaurant = Restaurant::factory()->create();

    $category = MenuCategory::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Grilled Chicken',
        'description' => 'Grilled chicken with herbs',
        'translations' => [
            'en' => 'Grilled Chicken',
        ],
    ]);

    app()->setLocale('ar');

    expect($item->translatedName)->toBe('Grilled Chicken');
});
