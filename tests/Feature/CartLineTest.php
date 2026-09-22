<?php

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Support\Money;

function createCartLineTestContext(): array
{
    $restaurant = Restaurant::factory()->create([
        'currency' => 'USD',
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

    return [
        'restaurant' => $restaurant,
        'table' => $table,
        'session' => $session,
        'category' => $category,
    ];
}

it('calculates the line total from the item variant addons and quantity', function (): void {
    $context = createCartLineTestContext();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'menu_category_id' => $context['category']->id,
        'name' => 'Lamb Kofta',
        'price' => Money::fromMinor(1800, 'USD'),
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $variant = MenuItemVariant::factory()->create([
        'menu_item_id' => $item->id,
        'label' => 'Sharing',
        'price_delta' => 1400,
        'is_default' => false,
    ]);

    $firstAddon = MenuItemAddon::factory()->create([
        'menu_item_id' => $item->id,
        'label' => 'Extra Sauce',
        'price_delta' => 200,
        'is_available' => true,
    ]);

    $secondAddon = MenuItemAddon::factory()->create([
        'menu_item_id' => $item->id,
        'label' => 'Pickles',
        'price_delta' => 150,
        'is_available' => true,
    ]);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('cart.lines.store'), [
            'menu_item_id' => $item->id,
            'variant_id' => $variant->id,
            'addon_ids' => [
                $firstAddon->id,
                $secondAddon->id,
            ],
            'qty' => 2,
        ]);

    $response->assertSessionHasNoErrors();

    $cart = Cart::query()
        ->where('table_session_id', $context['session']->id)
        ->firstOrFail();

    $line = CartLine::query()
        ->with('addons')
        ->where('cart_id', $cart->id)
        ->firstOrFail();

    expect($cart->currency)
        ->toBe('USD')
        ->and($line->menu_item_id)
        ->toBe($item->id)
        ->and($line->menu_item_variant_id)
        ->toBe($variant->id)
        ->and($line->name_snapshot)
        ->toBe('Lamb Kofta')
        ->and($line->variant_label_snapshot)
        ->toBe('Sharing')
        ->and($line->unit_price->amount())
        ->toBe(1800)
        ->and($line->variant_price_delta->amount())
        ->toBe(1400)
        ->and($line->qty)
        ->toBe(2)
        ->and($line->line_total->amount())
        ->toBe(7100)
        ->and($line->addons)
        ->toHaveCount(2);
});

it('ignores a client submitted price and recomputes the total on the server', function (): void {
    $context = createCartLineTestContext();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'menu_category_id' => $context['category']->id,
        'name' => 'Lamb Kofta',
        'price' => Money::fromMinor(1800, 'USD'),
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('cart.lines.store'), [
            'menu_item_id' => $item->id,
            'qty' => 2,

            // These values must never be trusted by the server.
            'price' => 1,
            'unit_price' => 1,
            'line_total' => 2,
        ]);

    $response->assertSessionHasNoErrors();

    $line = CartLine::query()->firstOrFail();

    expect($line->unit_price->amount())
        ->toBe(1800)
        ->and($line->line_total->amount())
        ->toBe(3600);
});

it('rejects a sold out menu item', function (): void {
    $context = createCartLineTestContext();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'menu_category_id' => $context['category']->id,
        'name' => 'Whole Sea Bass',
        'price' => Money::fromMinor(2400, 'USD'),
        'is_available' => false,
        'is_scheduled' => false,
    ]);

    $response = $this
        ->from(route('menu'))
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('cart.lines.store'), [
            'menu_item_id' => $item->id,
            'qty' => 1,
        ]);

    $response
        ->assertRedirect(route('menu'))
        ->assertSessionHasErrors('menu_item_id');

    expect(CartLine::query()->count())->toBe(0);
});

it('rejects a sold out addon', function (): void {
    $context = createCartLineTestContext();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'menu_category_id' => $context['category']->id,
        'name' => 'Lamb Kofta',
        'price' => Money::fromMinor(1800, 'USD'),
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $addon = MenuItemAddon::factory()
        ->soldOut()
        ->create([
            'menu_item_id' => $item->id,
            'label' => 'Extra Sauce',
            'price_delta' => 200,
        ]);

    $response = $this
        ->from(route('menu'))
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('cart.lines.store'), [
            'menu_item_id' => $item->id,
            'addon_ids' => [$addon->id],
            'qty' => 1,
        ]);

    $response
        ->assertRedirect(route('menu'))
        ->assertSessionHasErrors('addon_ids');

    expect(CartLine::query()->count())->toBe(0);
});

it('persists the kitchen note and strips control characters', function (): void {
    $context = createCartLineTestContext();

    $item = MenuItem::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'menu_category_id' => $context['category']->id,
        'name' => 'Lamb Kofta',
        'price' => Money::fromMinor(1800, 'USD'),
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('cart.lines.store'), [
            'menu_item_id' => $item->id,
            'qty' => 1,
            'note' => "  No onions\x00 please  ",
        ]);

    $response->assertSessionHasNoErrors();

    $line = CartLine::query()->firstOrFail();

    expect($line->note)->toBe('No onions please');
});
