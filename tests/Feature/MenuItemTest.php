<?php

use App\CurrentRestaurant;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('menu item belongs to the current restaurant and category', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'price' => 1250,
    ]);

    expect($item->restaurant_id)->toBe($restaurant->id)
        ->and($item->menu_category_id)->toBe($category->id)
        ->and($item->category->is($category))->toBeTrue()
        ->and($item->price)->toBeInstanceOf(Money::class)
        ->and($item->price->amount())->toBe(1250);
});

test('available now returns only currently available menu items', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $available = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $soldOut = MenuItem::factory()
        ->soldOut()
        ->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
        ]);

    $items = MenuItem::query()
        ->availableNow()
        ->get();

    expect($items->contains($available))->toBeTrue()
        ->and($items->contains($soldOut))->toBeFalse();
});

test('sold out items remain visible in the menu', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $soldOut = MenuItem::factory()
        ->soldOut()
        ->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
        ]);

    $items = MenuItem::query()
        ->where('restaurant_id', $restaurant->id)
        ->where('menu_category_id', $category->id)
        ->get();

    expect($items->contains($soldOut))->toBeTrue()
        ->and($soldOut->is_available)->toBeFalse();
});

