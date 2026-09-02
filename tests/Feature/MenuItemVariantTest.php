<?php

use App\CurrentRestaurant;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('menu item variant belongs to menu item', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
    ]);

    $variant = MenuItemVariant::factory()->create([
        'menu_item_id' => $item->id,
        'label' => 'Large',
        'price_delta' => 250,
        'is_default' => true,
        'sort_order' => 1,
    ]);

    expect($variant->menu_item_id)->toBe($item->id)
        ->and($variant->menuItem->is($item))->toBeTrue()
        ->and($variant->price_delta)->toBeInstanceOf(Money::class)
        ->and($variant->price_delta->amount())->toBe(250);
});

test('menu item returns variants ordered by sort order', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
    ]);

    $second = MenuItemVariant::factory()->create([
        'menu_item_id' => $item->id,
        'sort_order' => 2,
    ]);

    $first = MenuItemVariant::factory()->create([
        'menu_item_id' => $item->id,
        'sort_order' => 1,
    ]);

    $variants = $item->variants()->get();

    expect($variants->first()->id)->toBe($first->id)
        ->and($variants->last()->id)->toBe($second->id);
});
