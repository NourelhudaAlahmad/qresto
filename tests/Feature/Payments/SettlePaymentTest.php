<?php

use App\Actions\Orders\PlaceOrder;
use App\Actions\Payments\SettlePayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeSettlePaymentCart(): Cart
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
        ]);

    CartLine::factory()
        ->forCart($cart)
        ->create([
            'name_snapshot' => 'Lamb kofta',
            'unit_price' => Money::fromMinor(2000, 'TRY'),
            'variant_price_delta' => Money::fromMinor(0, 'TRY'),
            'line_total' => Money::fromMinor(2000, 'TRY'),
            'qty' => 1,
        ]);

    return $cart->fresh();
}

it('settles a 3ds card after the challenge is completed', function (): void {
    $cart = makeSettlePaymentCart();

    $placed = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::CARD,
        tipAmount: Money::fromMinor(0, 'TRY'),
        paymentPayload: [
            'card_number' => '4000002500003155',
        ],
    );

    $payment = $placed['payment'];

    expect($payment->status)
        ->toBe(PaymentStatus::REQUIRES_ACTION->value)
        ->and($payment->requires_3ds)->toBeTrue()
        ->and($placed['order']->is_paid)->toBeFalse();

    $settled = app(SettlePayment::class)->handle(
        payment: $payment,
        payload: [
            'three_ds_complete' => true,
        ],
    );

    $payment = $settled['payment'];
    $order = $payment->order()->firstOrFail();

    expect($settled['failure'])->toBeNull()
        ->and($settled['requires_action'])->toBeFalse()
        ->and($payment->status)->toBe(PaymentStatus::PAID->value)
        ->and($payment->requires_3ds)->toBeFalse()
        ->and($payment->paid_at)->not->toBeNull()
        ->and($order->is_paid)->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();
});

it('keeps a 3ds payment waiting when the challenge is not completed', function (): void {
    $cart = makeSettlePaymentCart();

    $placed = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::CARD,
        tipAmount: Money::fromMinor(0, 'TRY'),
        paymentPayload: [
            'card_number' => '4000002500003155',
        ],
    );

    $settled = app(SettlePayment::class)->handle(
        payment: $placed['payment'],
    );

    $payment = $settled['payment'];
    $order = $payment->order()->firstOrFail();

    expect($settled['failure'])->toBeNull()
        ->and($settled['requires_action'])->toBeTrue()
        ->and($settled['client_secret'])->not->toBeNull()
        ->and($payment->status)
        ->toBe(PaymentStatus::REQUIRES_ACTION->value)
        ->and($payment->requires_3ds)->toBeTrue()
        ->and($order->is_paid)->toBeFalse();
});

it('settles a cash payment by a staff member', function (): void {
    $cart = makeSettlePaymentCart();

    $placed = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::CASH,
        tipAmount: Money::fromMinor(0, 'TRY'),
    );

    $staff = User::factory()->create([
        'restaurant_id' => $cart->restaurant_id,
    ]);

    $settled = app(SettlePayment::class)->handle(
        payment: $placed['payment'],
        takenBy: $staff,
    );

    $payment = $settled['payment'];
    $order = $payment->order()->firstOrFail();

    expect($settled['failure'])->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::PAID->value)
        ->and($payment->taken_by)->toBe($staff->id)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->gateway)->toBeNull()
        ->and($payment->gateway_intent_id)->toBeNull()
        ->and($order->is_paid)->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();
});

it('settles a card at table payment by a staff member', function (): void {
    $cart = makeSettlePaymentCart();

    $placed = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::POS,
        tipAmount: Money::fromMinor(0, 'TRY'),
    );

    $staff = User::factory()->create([
        'restaurant_id' => $cart->restaurant_id,
    ]);

    $settled = app(SettlePayment::class)->handle(
        payment: $placed['payment'],
        takenBy: $staff,
    );

    $payment = $settled['payment'];
    $order = $payment->order()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::PAID->value)
        ->and($payment->taken_by)->toBe($staff->id)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->gateway)->toBeNull()
        ->and($order->is_paid)->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();
});

it('rejects settling an offline payment without a staff member', function (): void {
    $cart = makeSettlePaymentCart();

    $placed = app(PlaceOrder::class)->handle(
        cart: $cart,
        method: PaymentMethod::CASH,
        tipAmount: Money::fromMinor(0, 'TRY'),
    );

    expect(
        fn () => app(SettlePayment::class)->handle(
            payment: $placed['payment'],
        ),
    )->toThrow(
        ValidationException::class,
        'A staff member is required to settle this payment.',
    );

    $payment = $placed['payment']->fresh();

    expect($payment->status)->toBe(PaymentStatus::PENDING->value)
        ->and($payment->paid_at)->toBeNull();
});
