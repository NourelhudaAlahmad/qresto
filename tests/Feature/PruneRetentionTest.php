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
use App\Models\ShiftSignInToken;
use App\Models\TableSession;
use App\Models\User;
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

it('prunes shift sign-in codes a month after they expired', function () {
    $waiter = User::factory()->create(['restaurant_id' => $this->restaurant->id]);

    $code = fn (int $daysAgo, string $token) => ShiftSignInToken::query()->withoutGlobalScopes()->create([
        'restaurant_id' => $this->restaurant->id,
        'user_id' => $waiter->id,
        'token' => hash('sha256', $token),
        'issued_by' => $waiter->id,
        'expires_at' => now()->subDays($daysAgo),
    ]);

    $old = $code(31, 'old');
    $recent = $code(29, 'recent');

    prune();

    expect(ShiftSignInToken::query()->withoutGlobalScopes()->pluck('id')->all())->toBe([$recent->id])
        ->and($old->fresh())->toBeNull();
});

it('prunes notifications read long ago, and never unread ones', function () {
    $user = User::factory()->create(['restaurant_id' => $this->restaurant->id]);

    $notification = fn (string $id, ?int $readDaysAgo, int $createdDaysAgo) => DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'test',
        'notifiable_type' => User::class,
        'notifiable_id' => $user->id,
        'data' => '{}',
        'read_at' => $readDaysAgo === null ? null : now()->subDays($readDaysAgo),
        'created_at' => now()->subDays($createdDaysAgo),
        'updated_at' => now()->subDays($createdDaysAgo),
    ]);

    $notification('read-long-ago', 91, 120);
    $notification('read-recently', 10, 120);
    $notification('never-read', null, 400);

    $this->artisan('qresto:prune-notifications')->assertSuccessful();

    expect(DB::table('notifications')->orderBy('id')->pluck('id')->all())
        ->toBe(['never-read', 'read-recently']);
});

it('schedules the other retention jobs every day', function (string $command, string $argument) {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command ?? '', $command)
            && str_contains($event->command ?? '', $argument));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 0 * * *');
})->with([
    ['qresto:prune-notifications', ''],
    ['queue:prune-failed', '--hours=168'],
    ['auth:clear-resets', 'users'],
    ['auth:clear-resets', 'staff_invites'],
]);
