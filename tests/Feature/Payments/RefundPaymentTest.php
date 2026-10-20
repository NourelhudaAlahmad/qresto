<?php

use App\Actions\Payments\RefundPayment;
use App\Enums\Capability;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

function makeRefundablePayment(
    string $method = 'card',
    ?string $gateway = 'fake',
): Payment {
    $restaurant = Restaurant::factory()->create([
        'currency' => 'TRY',
    ]);

    $order = Order::factory()
        ->for($restaurant)
        ->create([
            'currency' => 'TRY',
            'is_paid' => true,
            'paid_at' => now(),
        ]);

    return Payment::factory()
        ->forOrder($order)
        ->paid()
        ->create([
            'method' => $method,
            'amount' => Money::fromMinor(10000, 'TRY'),
            'tip_amount' => Money::fromMinor(0, 'TRY'),
            'gateway' => $gateway,
            'gateway_intent_id' => $gateway !== null
                ? 'fake_success_refund-test'
                : null,
            'gateway_status' => $gateway !== null
                ? 'succeeded'
                : null,
            'refunded_amount' => Money::fromMinor(0, 'TRY'),
        ]);
}

function makeRefundUser(int $restaurantId): User
{
    $user = User::factory()->create([
        'restaurant_id' => $restaurantId,
    ]);

    Permission::findOrCreate(
        Capability::TAKE_PAYMENT->value,
        'web',
    );

    $user->givePermissionTo(
        Capability::TAKE_PAYMENT->value,
    );

    return $user;
}

it('partially refunds a paid gateway payment', function (): void {
    $payment = makeRefundablePayment();

    $actor = makeRefundUser(
        $payment->order->restaurant_id,
    );

    $result = app(RefundPayment::class)->handle(
        payment: $payment,
        amount: Money::fromMinor(2500, 'TRY'),
        actor: $actor,
    );

    $payment = $result['payment'];

    expect($result['failure'])->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::PAID->value)
        ->and($payment->refunded_amount->amount())->toBe(2500)
        ->and($payment->refunded_at)->not->toBeNull();
});

it('marks a payment refunded when the full amount has been refunded', function (): void {
    $payment = makeRefundablePayment();

    $actor = makeRefundUser(
        $payment->order->restaurant_id,
    );

    $result = app(RefundPayment::class)->handle(
        payment: $payment,
        amount: Money::fromMinor(10000, 'TRY'),
        actor: $actor,
    );

    $payment = $result['payment'];

    expect($result['failure'])->toBeNull()
        ->and($payment->status)
        ->toBe(PaymentStatus::REFUNDED->value)
        ->and($payment->refunded_amount->amount())->toBe(10000)
        ->and($payment->refunded_at)->not->toBeNull();
});

it('accumulates multiple partial refunds', function (): void {
    $payment = makeRefundablePayment();

    $actor = makeRefundUser(
        $payment->order->restaurant_id,
    );

    $first = app(RefundPayment::class)->handle(
        payment: $payment,
        amount: Money::fromMinor(2500, 'TRY'),
        actor: $actor,
    );

    $second = app(RefundPayment::class)->handle(
        payment: $first['payment'],
        amount: Money::fromMinor(3000, 'TRY'),
        actor: $actor,
    );

    expect($second['failure'])->toBeNull()
        ->and($second['payment']->status)
        ->toBe(PaymentStatus::PAID->value)
        ->and($second['payment']->refunded_amount->amount())
        ->toBe(5500);
});

it('rejects a refund larger than the remaining payment amount', function (): void {
    $payment = makeRefundablePayment();

    $actor = makeRefundUser(
        $payment->order->restaurant_id,
    );

    app(RefundPayment::class)->handle(
        payment: $payment,
        amount: Money::fromMinor(8000, 'TRY'),
        actor: $actor,
    );

    $payment->refresh();

    expect(
        fn () => app(RefundPayment::class)->handle(
            payment: $payment,
            amount: Money::fromMinor(3000, 'TRY'),
            actor: $actor,
        ),
    )->toThrow(
        ValidationException::class,
        'The refund amount exceeds the remaining payment amount.',
    );

    expect($payment->fresh()->refunded_amount->amount())
        ->toBe(8000);
});

it('rejects a refund from a user without the take payment capability', function (): void {
    $payment = makeRefundablePayment();

    $waiter = User::factory()->create([
        'restaurant_id' => $payment->order->restaurant_id,
    ]);

    expect(
        fn () => app(RefundPayment::class)->handle(
            payment: $payment,
            amount: Money::fromMinor(1000, 'TRY'),
            actor: $waiter,
        ),
    )->toThrow(AuthorizationException::class);

    expect($payment->fresh()->refunded_amount->amount())
        ->toBe(0);
});

it('refunds an offline cash payment without touching a gateway', function (): void {
    $payment = makeRefundablePayment(
        method: 'cash',
        gateway: null,
    );

    $actor = makeRefundUser(
        $payment->order->restaurant_id,
    );

    $result = app(RefundPayment::class)->handle(
        payment: $payment,
        amount: Money::fromMinor(2000, 'TRY'),
        actor: $actor,
    );

    expect($result['failure'])->toBeNull()
        ->and($result['payment']->status)
        ->toBe(PaymentStatus::PAID->value)
        ->and($result['payment']->refunded_amount->amount())
        ->toBe(2000);
});

it('rejects refunding an unpaid payment', function (): void {
    $payment = makeRefundablePayment();

    $payment->update([
        'status' => PaymentStatus::PENDING->value,
        'paid_at' => null,
    ]);

    $actor = makeRefundUser(
        $payment->order->restaurant_id,
    );

    expect(
        fn () => app(RefundPayment::class)->handle(
            payment: $payment->fresh(),
            amount: Money::fromMinor(1000, 'TRY'),
            actor: $actor,
        ),
    )->toThrow(
        ValidationException::class,
        'Only paid payments can be refunded.',
    );
});
