<?php

use App\CurrentRestaurant;
use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('allergen belongs to the current restaurant', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $allergen = Allergen::factory()
        ->create([
            'restaurant_id' => $restaurant->id,
        ]);

    expect($allergen->restaurant_id)->toBe($restaurant->id)
        ->and($allergen->restaurant->is($restaurant))->toBeTrue();
});

test('allergen can be attached to menu item', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
    ]);

    $allergen = Allergen::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $item->allergens()->attach($allergen->id, [
        'may_contain' => true,
    ]);

    expect($item->allergens)->toHaveCount(1)
        ->and($item->allergens->first()->is($allergen))->toBeTrue()
        ->and((bool) $item->allergens->first()->pivot->may_contain)->toBeTrue();
});

test('allergen returns attached menu items', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $category = MenuCategory::factory()
        ->forRestaurant($restaurant)
        ->create();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
    ]);

    $allergen = Allergen::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $allergen->menuItems()->attach($item->id, [
        'may_contain' => false,
    ]);

    expect($allergen->menuItems)->toHaveCount(1)
        ->and($allergen->menuItems->first()->is($item))->toBeTrue()
        ->and((bool) $allergen->menuItems->first()->pivot->may_contain)->toBeFalse();
});
