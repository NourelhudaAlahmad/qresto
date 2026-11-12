<?php

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\WebhookEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->restaurant = Restaurant::factory()->create();
    $this->table = RestaurantTable::factory()->forRestaurant($this->restaurant)->create();
});

/**
 * A guest session last active the given number of days ago, with a cart
 * holding two lines.
 */
function quietSession(RestaurantTable $table, int $daysAgo, bool $closed = true): TableSession
{
    $lastSeen = now()->subDays($daysAgo);

    $session = TableSession::factory()->forTable($table)->create([
        'opened_at' => $lastSeen->copy()->subHour(),
        'last_seen_at' => $lastSeen,
        'closed_at' => $closed ? $lastSeen->copy()->addMinutes(30) : null,
    ]);

    $cart = Cart::factory()->create([
        'restaurant_id' => $table->restaurant_id,
        'table_session_id' => $session->id,
    ]);

    CartLine::factory()->count(2)->create(['cart_id' => $cart->id]);

    return $session;
}

function prune(): void
{
    test()->artisan('model:prune')->assertSuccessful();
}

it('prunes a scan that never became an order, with its cart and cart lines', function () {
    $session = quietSession($this->table, 31);
    $cartId = $session->cart->id;

    prune();

    expect(TableSession::query()->whereKey($session->id)->exists())->toBeFalse()
        ->and(Cart::query()->whereKey($cartId)->exists())->toBeFalse()
        ->and(CartLine::query()->where('cart_id', $cartId)->count())->toBe(0);
});

it('keeps a session inside the retention window', function () {
    $session = quietSession($this->table, 29);

    prune();

    expect(TableSession::query()->whereKey($session->id)->exists())->toBeTrue();
});

it('never prunes a session with an order, and leaves the order whole', function (bool $paid) {
    $session = quietSession($this->table, 400);

    $order = Order::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'table_id' => $this->table->id,
        'table_session_id' => $session->id,
        'is_paid' => $paid,
        'status' => $paid ? 'paid' : 'served',
        'service_date' => now()->subDays(400)->toDateString(),
    ]);
    $lines = OrderLine::factory()->count(3)->create(['order_id' => $order->id]);
    $payment = Payment::factory()->forOrder($order)->create();
    $event = OrderEvent::factory()->create(['order_id' => $order->id]);

    prune();

    expect(TableSession::query()->whereKey($session->id)->exists())->toBeTrue()
        // The cart is dead weight: the order copied its lines.
        ->and(Cart::query()->where('table_session_id', $session->id)->exists())->toBeFalse()
        ->and($order->fresh()->table_session_id)->toBe($session->id)
        ->and(OrderLine::query()->where('order_id', $order->id)->pluck('id')->all())->toEqualCanonicalizing($lines->pluck('id')->all())
        ->and(Payment::query()->whereKey($payment->id)->exists())->toBeTrue()
        ->and(OrderEvent::query()->whereKey($event->id)->exists())->toBeTrue();
})->with([
    'paid' => true,
    'unpaid' => false,
]);

it('drops an abandoned cart on a session that was never closed', function () {
    $session = quietSession($this->table, 8, closed: false);

    prune();

    // The cart is past its seven days; the session is not past thirty.
    expect(Cart::query()->where('table_session_id', $session->id)->exists())->toBeFalse()
        ->and(TableSession::query()->whereKey($session->id)->exists())->toBeTrue();
});

it('keeps the cart of a guest who is still at the table', function () {
    $session = quietSession($this->table, 0, closed: false);

    prune();

    expect(Cart::query()->where('table_session_id', $session->id)->exists())->toBeTrue();
});

it('keeps a pruned session\'s service requests, unlinked', function () {
    $session = quietSession($this->table, 31);
    $request = ServiceRequest::factory()->forSession($session)->create();

    prune();

    expect($request->fresh())->not->toBeNull()
        ->and($request->fresh()->table_session_id)->toBeNull();
});

it('prunes webhook replay guards past the retention window only', function () {
    DB::table('webhook_events')->insert([
        ['provider' => 'stripe', 'event_id' => 'evt_old', 'type' => 'payment_intent.succeeded', 'received_at' => now()->subDays(31)],
        ['provider' => 'stripe', 'event_id' => 'evt_recent', 'type' => 'payment_intent.succeeded', 'received_at' => now()->subDays(29)],
    ]);

    prune();

    expect(WebhookEvent::query()->pluck('event_id')->all())->toBe(['evt_recent']);
});

it('is safe to run on an empty database, and twice', function () {
    prune();

    $session = quietSession($this->table, 31);
    $kept = quietSession($this->table, 1);

    prune();
    prune();

    expect(TableSession::query()->pluck('id')->all())->toBe([$kept->id])
        ->and($session->fresh())->toBeNull();
});

it('runs the prune from the schedule every day', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command ?? '', 'model:prune'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
});
