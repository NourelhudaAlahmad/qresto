<?php

use App\CurrentRestaurant;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('menu item addon belongs to menu item', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
    ]);

    $addon = MenuItemAddon::factory()->create([
        'menu_item_id' => $item->id,
        'label' => 'Extra Cheese',
        'price_delta' => 300,
        'is_available' => true,
        'sort_order' => 1,
    ]);

    expect($addon->menu_item_id)->toBe($item->id)
        ->and($addon->menuItem->is($item))->toBeTrue()
        ->and($addon->price_delta)->toBeInstanceOf(Money::class)
        ->and($addon->price_delta->amount())->toBe(300);
});

test('menu item returns addons ordered by sort order', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
    ]);

    $second = MenuItemAddon::factory()->create([
        'menu_item_id' => $item->id,
        'sort_order' => 2,
    ]);

    $first = MenuItemAddon::factory()->create([
        'menu_item_id' => $item->id,
        'sort_order' => 1,
    ]);

    $addons = $item->addons()->get();

    expect($addons->first()->id)->toBe($first->id)
        ->and($addons->last()->id)->toBe($second->id);
});

test('unavailable addon is not available', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
    ]);

    $addon = MenuItemAddon::factory()->create([
        'menu_item_id' => $item->id,
        'is_available' => false,
    ]);

    expect($addon->is_available)->toBeFalse();
});

