<?php

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function menuQueryCount(): int
{
    return collect(DB::getQueryLog())
        ->reject(function (array $query): bool {
            return str_starts_with(
                strtolower($query['query']),
                'update "table_sessions" set "last_seen_at"',
            );
        })
        ->count();
}

it('renders menu categories in sort order instead of alphabetical order', function (): void {
    $restaurant = Restaurant::factory()->create();

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $session = TableSession::factory()->create([
        'restaurant_id' => $restaurant->id,
        'restaurant_table_id' => $table->id,
        'opened_at' => now(),
        'last_seen_at' => now(),
        'closed_at' => null,
    ]);

    $desserts = MenuCategory::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Desserts',
        'sort_order' => 20,
        'is_active' => true,
    ]);

    $drinks = MenuCategory::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Drinks',
        'sort_order' => 30,
        'is_active' => true,
    ]);

    $mains = MenuCategory::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Mains',
        'sort_order' => 10,
        'is_active' => true,
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $mains->id,
        'name' => 'Lamb Kofta',
        'sort_order' => 10,
        'is_available' => true,
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $desserts->id,
        'name' => 'Baklava',
        'sort_order' => 10,
        'is_available' => true,
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $drinks->id,
        'name' => 'Mint Lemonade',
        'sort_order' => 10,
        'is_available' => true,
    ]);

    $response = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('guest/menu')
            ->has('categories', 3)
            ->where('categories.0.id', $mains->id)
            ->where('categories.1.id', $desserts->id)
            ->where('categories.2.id', $drinks->id),
    );
});

it('keeps sold out items in the menu payload', function (): void {
    $restaurant = Restaurant::factory()->create();

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
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
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $soldOutItem = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Whole Sea Bass',
        'sort_order' => 10,
        'is_available' => false,
    ]);

    $response = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where(
                'categories.0.items.0.id',
                $soldOutItem->id,
            )
            ->where(
                'categories.0.items.0.available',
                false,
            ),
    );
});

it('returns the session cart count and subtotal', function (): void {
    $restaurant = Restaurant::factory()->create([
        'currency' => 'TRY',
    ]);

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
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
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $kofta = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Lamb Kofta',
        'price' => Money::fromMinor(1800, 'TRY'),
        'is_available' => true,
    ]);

    $lemonade = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Mint Lemonade',
        'price' => Money::fromMinor(700, 'TRY'),
        'is_available' => true,
    ]);

    $cart = Cart::factory()
        ->forTableSession($session)
        ->create();

    CartLine::factory()
        ->forCart($cart)
        ->forMenuItem($kofta)
        ->quantity(2)
        ->create();

    CartLine::factory()
        ->forCart($cart)
        ->forMenuItem($lemonade)
        ->quantity(1)
        ->create();

    $response = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu'));

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->where('cart.count', 3)
            ->where('cart.subtotal.amount', 4300)
            ->where('cart.subtotal.currency', 'TRY'),
    );
});

it('keeps a fixed query budget as the menu grows', function (): void {
    $restaurant = Restaurant::factory()->create();

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
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
        'sort_order' => 10,
        'is_active' => true,
    ]);

    MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'First Item',
        'sort_order' => 10,
        'is_available' => true,
    ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $firstResponse = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu'));

    $firstResponse->assertOk();

    $smallMenuQueryCount = menuQueryCount();

    DB::disableQueryLog();

    MenuItem::factory()
        ->count(25)
        ->create([
            'restaurant_id' => $restaurant->id,
            'menu_category_id' => $category->id,
            'is_available' => true,
        ]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $secondResponse = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu'));

    $secondResponse->assertOk();

    $largeMenuQueryCount = menuQueryCount();

    DB::disableQueryLog();

    expect($largeMenuQueryCount)
        ->toBe($smallMenuQueryCount);
});

it('keeps a fixed query budget as the cart grows', function (): void {
    $restaurant = Restaurant::factory()->create([
        'currency' => 'TRY',
    ]);

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
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
        'sort_order' => 10,
        'is_active' => true,
    ]);

    $menuItem = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Lamb Kofta',
        'price' => Money::fromMinor(1800, 'TRY'),
        'is_available' => true,
    ]);

    $cart = Cart::factory()
        ->forTableSession($session)
        ->create();

    CartLine::factory()
        ->forCart($cart)
        ->forMenuItem($menuItem)
        ->quantity(1)
        ->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $firstResponse = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu'));

    $firstResponse->assertOk();

    $smallCartQueryCount = menuQueryCount();

    DB::disableQueryLog();

    CartLine::factory()
        ->count(25)
        ->forCart($cart)
        ->forMenuItem($menuItem)
        ->quantity(1)
        ->create();

    DB::flushQueryLog();
    DB::enableQueryLog();

    $secondResponse = $this
        ->withCookie('qresto_table_session', $session->token)
        ->get(route('menu'));

    $secondResponse->assertOk();

    $largeCartQueryCount = menuQueryCount();

    DB::disableQueryLog();

    expect($largeCartQueryCount)
        ->toBe($smallCartQueryCount);
});
