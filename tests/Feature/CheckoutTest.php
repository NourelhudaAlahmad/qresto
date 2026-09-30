<?php

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Support\Money;
use Inertia\Testing\AssertableInertia as Assert;

function createCheckoutTestContext(
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

function createCheckoutTestLine(
    array $context,
    int $qty = 2,
    int $unitPrice = 1800,
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
        'note' => null,
    ]);
}

it('shows checkout with subtotal service charge and total', function (): void {
    $context = createCheckoutTestContext('12.50');

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5435,
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(route('checkout'));

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('guest/checkout')
                ->where(
                    'restaurant.id',
                    $context['restaurant']->id,
                )
                ->where(
                    'restaurant.currency',
                    'USD',
                )
                ->where(
                    'table.number',
                    '12',
                )
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
                    'cart.discount_amount.amount',
                    0,
                )
                ->where(
                    'cart.total.amount',
                    6114,
                )
                ->where(
                    'promo.code',
                    null,
                )
                ->where(
                    'promo.discount.amount',
                    0,
                )
                ->where(
                    'gateway.intent_id',
                    null,
                )
                ->where(
                    'gateway.status',
                    null,
                )
                ->where(
                    'gateway.3ds_required',
                    false,
                ),
        );
});

it('does not include removed cart lines in checkout totals', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 2000,
    );

    CartLine::query()->create([
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
        'undo_token' => 'removed-checkout-line',
        'undo_expires_at' => now()->addSeconds(6),
    ]);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(route('checkout'));

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'cart.subtotal.amount',
                    2000,
                )
                ->where(
                    'cart.total.amount',
                    2000,
                ),
        );
});

it('redirects to cart when checkout cart has no active lines', function (): void {
    $context = createCheckoutTestContext();

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(route('checkout'));

    $response->assertRedirect(route('cart'));
});
it('places a cash order as unpaid', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), [
            'method' => 'cash',
            'tip_pct' => 0,
            'split_ways' => 1,
            'idempotency_key' => 'checkout-cash-test',
        ]);

    $order = Order::query()
        ->where(
            'idempotency_key',
            'checkout-cash-test',
        )
        ->firstOrFail();

    $payment = $order->payments()
        ->latest('id')
        ->firstOrFail();

    $response
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );

    expect($order->is_paid)
        ->toBeFalse()
        ->and($order->paid_at)
        ->toBeNull()
        ->and($order->split_ways)
        ->toBe(1)
        ->and($order->total->amount())
        ->toBe(5570)
        ->and($payment->method)
        ->toBe('cash')
        ->and($payment->status)
        ->toBe('pending')
        ->and($payment->paid_at)
        ->toBeNull();
});
it('places a card at table order as unpaid', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), [
            'method' => 'pos',
            'tip_pct' => 0,
            'split_ways' => 1,
            'idempotency_key' => 'checkout-pos-test',
        ]);

    $order = Order::query()
        ->where(
            'idempotency_key',
            'checkout-pos-test',
        )
        ->firstOrFail();

    $payment = $order->payments()
        ->latest('id')
        ->firstOrFail();

    $response
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );

    expect($order->is_paid)
        ->toBeFalse()
        ->and($order->paid_at)
        ->toBeNull()
        ->and($order->split_ways)
        ->toBe(1)
        ->and($order->total->amount())
        ->toBe(5570)
        ->and($payment->method)
        ->toBe('pos')
        ->and($payment->status)
        ->toBe('pending')
        ->and($payment->paid_at)
        ->toBeNull();
});
it('places and pays an order with a successful card', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), [
            'method' => 'card',
            'tip_pct' => 0,
            'split_ways' => 1,
            'idempotency_key' => 'checkout-card-success-test',
            'card_number' => '4242424242424242',
            'card_expiry' => '12/30',
            'card_cvc' => '123',
        ]);

    $order = Order::query()
        ->where(
            'idempotency_key',
            'checkout-card-success-test',
        )
        ->firstOrFail();

    $payment = $order->payments()
        ->latest('id')
        ->firstOrFail();

    $response
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );

    expect($order->is_paid)
        ->toBeTrue()
        ->and($order->paid_at)
        ->not->toBeNull()
        ->and($order->total->amount())
        ->toBe(5570)
        ->and($payment->method)
        ->toBe('card')
        ->and($payment->status)
        ->toBe('paid')
        ->and($payment->requires_3ds)
        ->toBeFalse()
        ->and($payment->failure_reason)
        ->toBeNull()
        ->and($payment->paid_at)
        ->not->toBeNull();
});
it('keeps checkout unplaced when the card is declined', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $ordersBefore = Order::query()->count();

    $response = $this
        ->from(route('checkout'))
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), [
            'method' => 'card',
            'tip_pct' => 0,
            'split_ways' => 1,
            'idempotency_key' => 'checkout-decline-test',
            'card_number' => '4000000000000002',
            'card_expiry' => '12/30',
            'card_cvc' => '123',
        ]);

    $response
        ->assertRedirect(route('checkout'))
        ->assertSessionHasErrors('card_cvc');

    expect(Order::query()->count())
        ->toBe($ordersBefore);

    expect(
        Order::query()
            ->where(
                'idempotency_key',
                'checkout-decline-test',
            )
            ->exists(),
    )->toBeFalse();
});
it('requires 3ds confirmation before marking the order as paid', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), [
            'method' => 'card',
            'tip_pct' => 0,
            'split_ways' => 1,
            'idempotency_key' => 'checkout-3ds-test',
            'card_number' => '4000002500003155',
            'card_expiry' => '12/30',
            'card_cvc' => '123',
        ]);

    $order = Order::query()
        ->where(
            'idempotency_key',
            'checkout-3ds-test',
        )
        ->firstOrFail();

    $payment = $order->payments()
        ->latest('id')
        ->firstOrFail();

    $response
        ->assertRedirect(route('checkout'))
        ->assertSessionHas(
            'checkout.pending_order_id',
            $order->id,
        );

    expect($order->is_paid)
        ->toBeFalse()
        ->and($order->paid_at)
        ->toBeNull()
        ->and($payment->status)
        ->toBe('requires_action')
        ->and($payment->requires_3ds)
        ->toBeTrue();

    $confirmResponse = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.confirm'), [
            'order_id' => $order->id,
            'confirmed' => true,
        ]);

    $order->refresh();
    $payment->refresh();

    $confirmResponse
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );

    expect($order->is_paid)
        ->toBeTrue()
        ->and($order->paid_at)
        ->not->toBeNull()
        ->and($payment->status)
        ->toBe('paid')
        ->and($payment->requires_3ds)
        ->toBeFalse()
        ->and($payment->paid_at)
        ->not->toBeNull();
});
it('creates only one order when the same idempotency key is submitted twice', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $payload = [
        'method' => 'cash',
        'tip_pct' => 0,
        'split_ways' => 1,
        'idempotency_key' => 'checkout-idempotency-test',
    ];

    $firstResponse = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), $payload);

    $secondResponse = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), $payload);

    $orders = Order::query()
        ->where(
            'idempotency_key',
            'checkout-idempotency-test',
        )
        ->get();

    expect($orders)
        ->toHaveCount(1);

    $order = $orders->first();

    expect($order)
        ->not->toBeNull()
        ->and($order->payments()->count())
        ->toBe(1);

    $firstResponse
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );

    $secondResponse
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );
});
it('adds the selected tip to the order total', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), [
            'method' => 'cash',
            'tip_pct' => 10,
            'split_ways' => 1,
            'idempotency_key' => 'checkout-tip-test',
        ]);

    $order = Order::query()
        ->where(
            'idempotency_key',
            'checkout-tip-test',
        )
        ->firstOrFail();

    $response
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );

    expect($order->subtotal->amount())
        ->toBe(5570)
        ->and($order->tip_amount->amount())
        ->toBe(557)
        ->and($order->total->amount())
        ->toBe(6127)
        ->and($order->is_paid)
        ->toBeFalse();
});
it('splits the order total evenly across three ways', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), [
            'method' => 'cash',
            'tip_pct' => 0,
            'split_ways' => 3,
            'idempotency_key' => 'checkout-split-three-test',
        ]);

    $order = Order::query()
        ->where(
            'idempotency_key',
            'checkout-split-three-test',
        )
        ->firstOrFail();

    $response
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );

    expect($order->split_ways)
        ->toBe(3)
        ->and($order->total->amount())
        ->toBe(5570)
        ->and($order->share_amount->amount())
        ->toBe(1857)
        ->and($order->is_paid)
        ->toBeFalse();
});
it('applies a percentage promo code to the order total', function (): void {
    $context = createCheckoutTestContext();

    createCheckoutTestLine(
        context: $context,
        qty: 1,
        unitPrice: 5570,
    );

    $promotion = Promotion::query()->create([
        'restaurant_id' => $context['restaurant']->id,
        'code' => 'SAVE10',
        'kind' => 'percent',
        'value' => '10.00',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'max_uses' => 10,
        'uses' => 0,
    ]);

    $response = $this
        ->withSession([
            'checkout.promo_code' => 'SAVE10',
        ])
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(route('checkout.store'), [
            'method' => 'cash',
            'tip_pct' => 0,
            'split_ways' => 1,
            'idempotency_key' => 'checkout-promo-test',
        ]);

    $order = Order::query()
        ->where(
            'idempotency_key',
            'checkout-promo-test',
        )
        ->firstOrFail();

    $promotion->refresh();

    $response
        ->assertRedirect(route('menu'))
        ->assertSessionHas(
            'placed_order_id',
            $order->id,
        );

    expect($order->subtotal->amount())
        ->toBe(5570)
        ->and($order->discount_amount->amount())
        ->toBe(557)
        ->and($order->total->amount())
        ->toBe(5013)
        ->and($promotion->uses)
        ->toBe(1);
});
