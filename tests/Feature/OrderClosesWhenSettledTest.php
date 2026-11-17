<?php

use App\Actions\Payments\SettlePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('closes a card order that was paid up front once it is served', function () {
    $order = Order::factory()->ready()->create([
        'total' => 2000,
        'is_paid' => true,
        'paid_at' => now(),
    ]);
    $runner = User::factory()->create(['restaurant_id' => $order->restaurant_id]);

    $order->transitionTo(OrderStatus::SERVED, $runner);

    expect($order->fresh()->status)->toBe(OrderStatus::PAID)
        ->and(OrderEvent::query()->where('order_id', $order->id)->pluck('to_status')->all())
        ->toBe([OrderStatus::SERVED->value, OrderStatus::PAID->value])
        ->and(OrderEvent::query()->where('to_status', 'paid')->value('actor_id'))->toBe($runner->id);
});

it('keeps an unpaid order at served until the bill is settled', function () {
    $order = Order::factory()->ready()->create(['total' => 2000]);

    $order->transitionTo(OrderStatus::SERVED);

    expect($order->fresh()->status)->toBe(OrderStatus::SERVED);
});

it('closes a served order when its last payment settles', function () {
    $order = Order::factory()->served()->create(['total' => 2000]);
    $cashier = User::factory()->create(['restaurant_id' => $order->restaurant_id]);

    $payment = Payment::factory()->forOrder($order)->create([
        'method' => 'cash',
        'status' => PaymentStatus::PENDING->value,
        'amount' => 2000,
        'tip_amount' => 0,
    ]);

    app(SettlePayment::class)->handle($payment, $cashier);

    $order->refresh();

    expect($order->is_paid)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::PAID)
        ->and(OrderEvent::query()->where('to_status', 'paid')->value('actor_id'))->toBe($cashier->id);
});

it('leaves a part-paid order open', function () {
    $order = Order::factory()->served()->create(['total' => 2000]);
    $cashier = User::factory()->create(['restaurant_id' => $order->restaurant_id]);

    $payment = Payment::factory()->forOrder($order)->create([
        'method' => 'cash',
        'status' => PaymentStatus::PENDING->value,
        'amount' => 500,
        'tip_amount' => 0,
    ]);

    app(SettlePayment::class)->handle($payment, $cashier);

    expect($order->fresh()->status)->toBe(OrderStatus::SERVED);
});

it('closes orders that were already stuck at served and paid', function () {
    $stuck = Order::factory()->served()->create(['is_paid' => true, 'paid_at' => now()]);
    $unpaid = Order::factory()->served()->create();

    $migration = require database_path('migrations/2026_11_17_100454_close_served_orders_that_are_already_paid.php');
    $migration->up();

    expect($stuck->fresh()->status)->toBe(OrderStatus::PAID)
        ->and($unpaid->fresh()->status)->toBe(OrderStatus::SERVED)
        ->and(DB::table('order_events')->where('order_id', $stuck->id)->where('to_status', 'paid')->count())->toBe(1);
});
