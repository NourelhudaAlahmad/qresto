<?php

use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('reads every line\'s and payment\'s money without a query per row', function () {
    $order = Order::factory()->create(['currency' => 'TRY']);
    OrderLine::factory()->count(4)->create(['order_id' => $order->id]);
    Payment::factory()->forOrder($order)->count(3)->create(['method' => 'cash']);

    $loaded = Order::query()->with(['lines', 'payments'])->findOrFail($order->id);

    DB::enableQueryLog();

    $currencies = $loaded->lines->map(fn (OrderLine $line): string => $line->line_total->currency())
        ->merge($loaded->payments->map(fn (Payment $payment): string => $payment->amount->currency()))
        ->unique()
        ->all();

    // Lazy loading through the relation knows its order too.
    $order->payments()->get()->each(fn (Payment $payment) => $payment->amount->currency());

    $orderLookups = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'from "orders"'));

    expect($currencies)->toBe(['TRY'])
        ->and($orderLookups)->toBeEmpty();
});
