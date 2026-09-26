<?php

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function createCartTestContext(
    string $serviceChargePct = '0',
): array {
    $restaurant = Restaurant::factory()->create([
        'currency' => 'USD',
        'service_charge_pct' => $serviceChargePct,
    ]);

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
        'number' => '12',
    ]);

    $session = TableSession::factory()->create([
        'restaurant_id' => $restaurant->id,
        'restaurant_table_id' => $table->id,
        'guest_name' => 'Nour Alahmad',
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

    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'name' => 'Lamb Kofta',
        'price' => Money::fromMinor(1800, 'USD'),
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $cart = Cart::query()->create([
        'restaurant_id' => $restaurant->id,
        'table_session_id' => $session->id,
        'currency' => 'USD',
    ]);

    return [
        'restaurant' => $restaurant,
        'table' => $table,
        'session' => $session,
        'category' => $category,
        'item' => $item,
        'cart' => $cart,
    ];
}

function createCartTestLine(
    array $context,
    int $qty = 2,
    int $unitPrice = 1800,
    ?string $note = 'No onions',
): CartLine {
    return CartLine::query()->create([
        'cart_id' => $context['cart']->id,
        'menu_item_id' => $context['item']->id,
        'menu_item_variant_id' => null,
        'name_snapshot' => 'Lamb Kofta',
        'variant_label_snapshot' => null,
        'unit_price' => Money::fromMinor($unitPrice, 'USD'),
        'variant_price_delta' => Money::fromMinor(0, 'USD'),
        'qty' => $qty,
        'line_total' => Money::fromMinor(
            $unitPrice * $qty,
            'USD',
        ),
        'note' => $note,
    ]);
}

it('removes a line when quantity becomes zero and returns an undo token', function (): void {
    config()->set('qresto.undo_window_seconds', 6);

    $context = createCartTestContext();
    $line = createCartTestLine($context);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->patch(
            route('cart.lines.update', $line),
            ['qty' => 0],
        );

    $response
        ->assertOk()
        ->assertJson([
            'removed' => true,
            'line_id' => $line->id,
            'undo_window_seconds' => 6,
        ])
        ->assertJsonStructure([
            'undo_token',
            'undo_expires_at',
        ]);

    $line->refresh();

    expect($line->removed_at)
        ->not->toBeNull()
        ->and($line->undo_token)
        ->not->toBeNull()
        ->and(strlen((string) $line->undo_token))
        ->toBe(64)
        ->and($line->undo_expires_at)
        ->not->toBeNull();
});

it('restores a removed line inside the undo window and preserves its configuration', function (): void {
    config()->set('qresto.undo_window_seconds', 6);

    $context = createCartTestContext();

    $addon = MenuItemAddon::factory()->create([
        'menu_item_id' => $context['item']->id,
        'label' => 'Extra Sauce',
        'price_delta' => 200,
        'is_available' => true,
    ]);

    $line = createCartTestLine(
        context: $context,
        qty: 2,
        unitPrice: 1800,
        note: 'No onions',
    );

    $line->addons()->create([
        'menu_item_addon_id' => $addon->id,
        'label_snapshot' => 'Extra Sauce',
        'price_delta' => Money::fromMinor(200, 'USD'),
    ]);

    $removeResponse = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->patch(
            route('cart.lines.update', $line),
            ['qty' => 0],
        );

    $removeResponse->assertOk();

    $undoToken = $removeResponse->json('undo_token');

    $restoreResponse = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(
            route('cart.lines.restore', $line),
            [
                'undo_token' => $undoToken,
            ],
        );

    $restoreResponse
        ->assertOk()
        ->assertJsonPath('restored', true)
        ->assertJsonPath('line.id', $line->id)
        ->assertJsonPath('line.qty', 2)
        ->assertJsonPath('line.note', 'No onions')
        ->assertJsonPath(
            'line.addons.0.label',
            'Extra Sauce',
        );

    $line->refresh();

    expect($line->removed_at)
        ->toBeNull()
        ->and($line->undo_token)
        ->toBeNull()
        ->and($line->undo_expires_at)
        ->toBeNull()
        ->and($line->qty)
        ->toBe(2)
        ->and($line->note)
        ->toBe('No onions')
        ->and($line->addons()->count())
        ->toBe(1);
});

it('returns gone when restoring after the undo window expires', function (): void {
    config()->set('qresto.undo_window_seconds', 6);

    Carbon::setTestNow('2026-09-23 12:00:00');

    $context = createCartTestContext();
    $line = createCartTestLine($context);

    $removeResponse = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->patch(
            route('cart.lines.update', $line),
            ['qty' => 0],
        );

    $removeResponse->assertOk();

    $undoToken = $removeResponse->json('undo_token');

    Carbon::setTestNow(
        now()->addSeconds(7),
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(
            route('cart.lines.restore', $line),
            [
                'undo_token' => $undoToken,
            ],
        );

    $response->assertStatus(410);

    $line->refresh();

    expect($line->removed_at)
        ->not->toBeNull();

    Carbon::setTestNow();
});

it('calculates subtotal service charge and total from restaurant settings', function (): void {
    $context = createCartTestContext('12.50');

    createCartTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5435,
        note: null,
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(route('cart'));

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('guest/cart')
                ->where(
                    'cart.subtotal.amount',
                    5435,
                )
                ->where(
                    'cart.service_pct',
                    '12.50',
                )
                ->where(
                    'cart.service_amount.amount',
                    679,
                )
                ->where(
                    'cart.total.amount',
                    6114,
                )
                ->where(
                    'table.number',
                    '12',
                )
                ->where(
                    'guest.first_name',
                    'Nour',
                ),
        );
});

it('does not include removed lines in cart totals or visible lines', function (): void {
    $context = createCartTestContext('12.50');

    $activeLine = createCartTestLine(
        context: $context,
        qty: 1,
        unitPrice: 2000,
        note: null,
    );

    $removedLine = CartLine::query()->create([
        'cart_id' => $context['cart']->id,
        'menu_item_id' => $context['item']->id,
        'menu_item_variant_id' => null,
        'name_snapshot' => 'Removed Lamb Kofta',
        'variant_label_snapshot' => null,
        'unit_price' => Money::fromMinor(3000, 'USD'),
        'variant_price_delta' => Money::fromMinor(0, 'USD'),
        'qty' => 1,
        'line_total' => Money::fromMinor(3000, 'USD'),
        'note' => null,
        'removed_at' => now(),
        'undo_token' => 'removed-line-token',
        'undo_expires_at' => now()->addSeconds(6),
    ]);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(route('cart'));

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->has('cart.lines', 1)
                ->where(
                    'cart.lines.0.id',
                    $activeLine->id,
                )
                ->where(
                    'cart.subtotal.amount',
                    2000,
                )
                ->where(
                    'cart.service_amount.amount',
                    250,
                )
                ->where(
                    'cart.total.amount',
                    2250,
                ),
        );

    expect($removedLine->exists)->toBeTrue();
});

it('updates the table note only for the current session cart', function (): void {
    $context = createCartTestContext();

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->patch(
            route('cart.note.update'),
            [
                'note' => "  One birthday candle\x00 for the knafeh  ",
            ],
        );

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('cart'));

    $context['cart']->refresh();

    expect($context['cart']->note)
        ->toBe('One birthday candle for the knafeh');
});

it('prevents another table session from updating or removing a cart line', function (): void {
    $context = createCartTestContext();
    $line = createCartTestLine($context);

    $otherTable = RestaurantTable::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
    ]);

    $otherSession = TableSession::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'restaurant_table_id' => $otherTable->id,
        'opened_at' => now(),
        'last_seen_at' => now(),
        'closed_at' => null,
    ]);

    $updateResponse = $this
        ->withCookie(
            'qresto_table_session',
            $otherSession->token,
        )
        ->patch(
            route('cart.lines.update', $line),
            ['qty' => 3],
        );

    $updateResponse->assertNotFound();

    $destroyResponse = $this
        ->withCookie(
            'qresto_table_session',
            $otherSession->token,
        )
        ->delete(
            route('cart.lines.destroy', $line),
        );

    $destroyResponse->assertNotFound();

    $line->refresh();

    expect($line->qty)
        ->toBe(2)
        ->and($line->removed_at)
        ->toBeNull();
});

it('prevents another table session from restoring a removed cart line', function (): void {
    config()->set('qresto.undo_window_seconds', 6);

    $context = createCartTestContext();
    $line = createCartTestLine($context);

    $removeResponse = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->patch(
            route('cart.lines.update', $line),
            ['qty' => 0],
        );

    $removeResponse->assertOk();

    $undoToken = $removeResponse->json('undo_token');

    $otherTable = RestaurantTable::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
    ]);

    $otherSession = TableSession::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'restaurant_table_id' => $otherTable->id,
        'opened_at' => now(),
        'last_seen_at' => now(),
        'closed_at' => null,
    ]);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $otherSession->token,
        )
        ->post(
            route('cart.lines.restore', $line),
            [
                'undo_token' => $undoToken,
            ],
        );

    $response->assertNotFound();

    $line->refresh();

    expect($line->removed_at)
        ->not->toBeNull();
});
