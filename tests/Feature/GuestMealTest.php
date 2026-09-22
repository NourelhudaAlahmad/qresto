<?php

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

it('returns the complete meal configuration payload', function (): void {
    $restaurant = Restaurant::factory()->create([
        'currency' => 'USD',
    ]);

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
        'number' => '01',
    ]);

    $session = TableSession::factory()->create([
        'restaurant_id' => $restaurant->id,
        'restaurant_table_id' => $table->id,
        'opened_at' => now(),
        'last_seen_at' => now(),
        'closed_at' => null,
    ]);

    $category = MenuCategory::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Mains',
        'translations' => [
            'en' => 'Mains',
            'ar' => 'الأطباق الرئيسية',
        ],
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Lamb Kofta',
        'description' => 'Charcoal-grilled lamb kofta.',
        'price' => Money::fromMinor(1800, 'USD'),
        'photo_path' => '/images/lamb-kofta.jpg',
        'prep_minutes' => 18,
        'is_available' => true,
        'is_scheduled' => false,
        'dietary_tags' => ['high-protein'],
        'chef_flag' => true,
    ]);

    $sharing = MenuItemVariant::factory()->create([
        'menu_item_id' => $item->id,
        'label' => 'Sharing',
        'price_delta' => 1400,
        'is_default' => true,
        'sort_order' => 10,
    ]);

    $regular = MenuItemVariant::factory()->create([
        'menu_item_id' => $item->id,
        'label' => 'Regular',
        'price_delta' => 0,
        'is_default' => false,
        'sort_order' => 20,
    ]);

    $availableAddon = MenuItemAddon::factory()->create([
        'menu_item_id' => $item->id,
        'label' => 'Extra Sauce',
        'price_delta' => 200,
        'is_available' => true,
        'sort_order' => 10,
    ]);

    $soldOutAddon = MenuItemAddon::factory()
        ->soldOut()
        ->create([
            'menu_item_id' => $item->id,
            'label' => 'Pickles',
            'price_delta' => 150,
            'sort_order' => 20,
        ]);

    $gluten = Allergen::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Gluten',
    ]);

    $nuts = Allergen::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Nuts',
    ]);

    $item->allergens()->attach($gluten->id, [
        'may_contain' => false,
    ]);

    $item->allergens()->attach($nuts->id, [
        'may_contain' => true,
    ]);

    $response = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu.items.show', $item));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('guest/meal')
            ->where('restaurant.id', $restaurant->id)
            ->where('restaurant.currency', 'USD')
            ->where('table.id', $table->id)
            ->where('table.number', '01')
            ->where('item.id', $item->id)
            ->where('item.name', 'Lamb Kofta')
            ->where(
                'item.description',
                'Charcoal-grilled lamb kofta.',
            )
            ->where('item.category', 'Mains')
            ->where('item.price.amount', 1800)
            ->where('item.price.currency', 'USD')
            ->where('item.photo', '/images/lamb-kofta.jpg')
            ->where('item.prep_minutes', 18)
            ->where('item.available', true)
            ->where('item.tags.0', 'high-protein')
            ->where('item.flag', true)
            ->has('item.variants', 2)
            ->where('item.variants.0.id', $sharing->id)
            ->where('item.variants.0.label', 'Sharing')
            ->where(
                'item.variants.0.price_delta.amount',
                1400,
            )
            ->where(
                'item.variants.0.price_delta.currency',
                'USD',
            )
            ->where('item.variants.0.is_default', true)
            ->where('item.variants.1.id', $regular->id)
            ->where('item.variants.1.label', 'Regular')
            ->where(
                'item.variants.1.price_delta.amount',
                0,
            )
            ->has('item.addons', 2)
            ->where(
                'item.addons.0.id',
                $availableAddon->id,
            )
            ->where(
                'item.addons.0.label',
                'Extra Sauce',
            )
            ->where(
                'item.addons.0.price_delta.amount',
                200,
            )
            ->where(
                'item.addons.0.price_delta.currency',
                'USD',
            )
            ->where(
                'item.addons.0.available',
                true,
            )
            ->where(
                'item.addons.1.id',
                $soldOutAddon->id,
            )
            ->where(
                'item.addons.1.available',
                false,
            )
            ->has('item.allergens', 2)
            ->where('item.allergens.0.id', $gluten->id)
            ->where(
                'item.allergens.0.may_contain',
                false,
            )
            ->where('item.allergens.1.id', $nuts->id)
            ->where(
                'item.allergens.1.may_contain',
                true,
            ),
    );
});

it('does not expose a meal from another restaurant session', function (): void {
    $sessionRestaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $sessionRestaurant->id,
    ]);

    $session = TableSession::factory()->create([
        'restaurant_id' => $sessionRestaurant->id,
        'restaurant_table_id' => $table->id,
        'opened_at' => now(),
        'last_seen_at' => now(),
        'closed_at' => null,
    ]);

    $category = MenuCategory::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
        'is_active' => true,
    ]);

    $item = MenuItem::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
        'menu_category_id' => $category->id,
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $response = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu.items.show', $item));

    $response->assertNotFound();
});
