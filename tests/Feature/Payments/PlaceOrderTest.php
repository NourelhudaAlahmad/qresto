<?php

use App\Actions\Orders\PlaceOrder;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Payments\Gateways\FakeGateway;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCheckoutCart(): Cart
{
    $restaurant = Restaurant::factory()->create([
        'currency' => 'TRY',
        'service_charge_pct' => 10,
    ]);

    $table = RestaurantTable::factory()
        ->forRestaurant($restaurant)
        ->create();

    $session = TableSession::factory()
        ->forTable($table)
        ->active()
        ->create([
            'guest_name' => 'Nour',
        ]);

    $cart = Cart::factory()
        ->forTableSession($session)
        ->create([
            'currency' => 'TRY',
            'note' => 'Window table',
        ]);

    CartLine::factory()
        ->forCart($cart)
        ->quantity(2)
        ->create([
            'name_snapshot' => 'Lamb kofta',
            'unit_price' => Money::fromMinor(1000, 'TRY'),
            'variant_price_delta' => Money::fromMinor(0, 'TRY'),
            'line_total' => Money::fromMinor(2000, 'TRY'),
        ]);

    return $cart->fresh();
}

it('places and pays an order with the successful fake card', function (): void {
    $cart = makeCheckoutCart();

    $result = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::CARD,
        tipAmount: Money::fromMinor(200, 'TRY'),
        paymentPayload: [
            'card_number' => FakeGateway::SUCCESS_CARD,
        ],
    );

    $order = $result['order'];
    $payment = $result['payment'];

    expect($result['failure'])->toBeNull()
        ->and($result['requires_action'])->toBeFalse()
        ->and($result['client_secret'])->toBeNull()
        ->and($order->is_paid)->toBeTrue()
        ->and($order->paid_at)->not->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::PAID->value)
        ->and($payment->gateway)->toBe('fake')
        ->and($payment->gateway_intent_id)->not->toBeNull()
        ->and($payment->paid_at)->not->toBeNull();

    expect($order->events()->count())->toBe(1)
        ->and($order->lines()->count())->toBe(1);

    expect($order->subtotal->amount())->toBe(2000)
        ->and($order->service_amount->amount())->toBe(200)
        ->and($order->tip_amount->amount())->toBe(200)
        ->and($order->total->amount())->toBe(2400);
});

it('returns requires action for the fake 3ds card without marking the order paid', function (): void {
    $cart = makeCheckoutCart();

    $result = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::CARD,
        tipAmount: Money::fromMinor(0, 'TRY'),
        paymentPayload: [
            'card_number' => FakeGateway::THREE_DS_CARD,
        ],
    );

    $order = $result['order'];
    $payment = $result['payment'];

    expect($result['failure'])->toBeNull()
        ->and($result['requires_action'])->toBeTrue()
        ->and($result['client_secret'])->not->toBeNull()
        ->and($order->is_paid)->toBeFalse()
        ->and($order->paid_at)->toBeNull()
        ->and($payment->status)->toBe(
            PaymentStatus::REQUIRES_ACTION->value,
        )
        ->and($payment->requires_3ds)->toBeTrue()
        ->and($payment->paid_at)->toBeNull();

    expect($order->events()->count())->toBe(1);
});

it('leaves a declined card order unpaid with a structured failure and no event', function (): void {
    $cart = makeCheckoutCart();

    $result = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::CARD,
        tipAmount: Money::fromMinor(0, 'TRY'),
        paymentPayload: [
            'card_number' => FakeGateway::DECLINE_CARD,
        ],
    );

    $order = $result['order'];
    $payment = $result['payment'];
    $failure = $result['failure'];

    expect($failure)->not->toBeNull()
        ->and($failure->code)->toBe('card_declined')
        ->and($failure->field)->toBe('card_number')
        ->and($failure->message)->toBe('Your card was declined.')
        ->and($result['requires_action'])->toBeFalse()
        ->and($result['client_secret'])->toBeNull();

    expect($order->exists)->toBeTrue()
        ->and($order->is_paid)->toBeFalse()
        ->and($order->paid_at)->toBeNull();

    expect($payment->exists)->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::FAILED->value)
        ->and($payment->gateway)->toBe('fake')
        ->and($payment->gateway_intent_id)->not->toBeNull()
        ->and($payment->gateway_status)->toBe('failed')
        ->and($payment->requires_3ds)->toBeFalse()
        ->and($payment->paid_at)->toBeNull()
        ->and($payment->failure_reason)->toBe(
            'Your card was declined.',
        );

    expect($order->events()->count())->toBe(0);

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'is_paid' => false,
    ]);

    $this->assertDatabaseHas('payments', [
        'id' => $payment->id,
        'order_id' => $order->id,
        'status' => PaymentStatus::FAILED->value,
        'gateway_status' => 'failed',
        'failure_reason' => 'Your card was declined.',
    ]);
});

it('places a cash order with a pending payment without a gateway', function (): void {
    $cart = makeCheckoutCart();

    $result = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::CASH,
        tipAmount: Money::fromMinor(0, 'TRY'),
    );

    $order = $result['order'];
    $payment = $result['payment'];

    expect($result['failure'])->toBeNull()
        ->and($result['requires_action'])->toBeFalse()
        ->and($order->is_paid)->toBeFalse()
        ->and($payment->status)->toBe(PaymentStatus::PENDING->value)
        ->and($payment->gateway)->toBeNull()
        ->and($payment->gateway_intent_id)->toBeNull()
        ->and($payment->paid_at)->toBeNull();

    expect($order->events()->count())->toBe(1);
});

it('places a card at table order with a pending payment without a gateway', function (): void {
    $cart = makeCheckoutCart();

    $result = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::POS,
        tipAmount: Money::fromMinor(0, 'TRY'),
    );

    $order = $result['order'];
    $payment = $result['payment'];

    expect($result['failure'])->toBeNull()
        ->and($result['requires_action'])->toBeFalse()
        ->and($order->is_paid)->toBeFalse()
        ->and($payment->status)->toBe(PaymentStatus::PENDING->value)
        ->and($payment->gateway)->toBeNull()
        ->and($payment->gateway_intent_id)->toBeNull();

    expect($order->events()->count())->toBe(1);
});
