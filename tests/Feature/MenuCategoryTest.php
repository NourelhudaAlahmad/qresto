<?php

use App\CurrentRestaurant;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('menu category belongs to the current restaurant', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    expect($category->restaurant_id)->toBe($restaurant->id);
});

test('categories are ordered by sort order', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create([
            'name' => 'Desserts',
            'sort_order' => 3,
        ]);

    MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create([
            'name' => 'Starters',
            'sort_order' => 1,
        ]);

    MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create([
            'name' => 'Main Courses',
            'sort_order' => 2,
        ]);

    $categories = MenuCategory::query()
        ->orderBy('sort_order')
        ->get();

    expect($categories->pluck('name')->all())
        ->toBe([
            'Starters',
            'Main Courses',
            'Desserts',
        ]);
});

test('category returns items ordered by sort order', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $category->items()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Item Three',
        'sort_order' => 3,
        'price' => 1000,
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $category->items()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Item One',
        'sort_order' => 1,
        'price' => 1000,
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $category->items()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Item Two',
        'sort_order' => 2,
        'price' => 1000,
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $items = $category->items()->get();

    expect($items->pluck('name')->all())
        ->toBe([
            'Item One',
            'Item Two',
            'Item Three',
        ]);
});

test('loading the full menu uses a bounded number of queries', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    MenuItem::factory()
        ->count(3)
        ->forRestaurant($restaurant)
        ->for($category, 'category')
        ->withVariants(2)
        ->withAddons(2)
        ->create();

    DB::enableQueryLog();

    MenuCategory::query()
        ->with([
            'items.variants',
            'items.addons',
            'items.allergens',
        ])
        ->where('restaurant_id', $restaurant->id)
        ->get();

    $queryCount = count(DB::getQueryLog());

    DB::disableQueryLog();

    expect($queryCount)->toBeLessThanOrEqual(5);
});
