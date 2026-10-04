<?php

use App\Enums\OrderStatus;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function createGuestOrderStatusContext(
    OrderStatus $status = OrderStatus::PLACED,
): array {
    $restaurant = Restaurant::factory()->create([
        'currency' => 'USD',
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
        'prep_minutes' => 12,
        'is_available' => true,
        'is_scheduled' => false,
    ]);

    $placedAt = Carbon::parse('2026-10-04 18:00:00');

    $order = Order::factory()
        ->withTableSession($session)
        ->create([
            'status' => $status,
            'placed_at' => $placedAt,
            'currency' => 'USD',
            'subtotal' => Money::fromMinor(1800, 'USD'),
            'service_pct' => '0',
            'service_amount' => Money::fromMinor(0, 'USD'),
            'tip_amount' => Money::fromMinor(0, 'USD'),
            'discount_amount' => Money::fromMinor(0, 'USD'),
            'total' => Money::fromMinor(1800, 'USD'),
            'is_paid' => $status === OrderStatus::PAID,
            'paid_at' => $status === OrderStatus::PAID
                ? $placedAt->copy()->addMinutes(20)
                : null,
        ]);

    OrderLine::query()->create([
        'order_id' => $order->id,
        'menu_item_id' => $item->id,
        'name_snapshot' => 'Lamb Kofta',
        'unit_price' => Money::fromMinor(1800, 'USD'),
        'qty' => 1,
        'line_total' => Money::fromMinor(1800, 'USD'),
        'note' => null,
        'station' => null,
    ]);

    return [
        'restaurant' => $restaurant,
        'table' => $table,
        'session' => $session,
        'category' => $category,
        'item' => $item,
        'order' => $order,
        'placed_at' => $placedAt,
    ];
}

it('shows the current session order status', function (): void {
    $context = createGuestOrderStatusContext();

    Payment::factory()
        ->forOrder($context['order'])
        ->pending()
        ->create([
            'method' => 'cash',
        ]);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(
            route(
                'guest.orders.status',
                $context['order'],
            ),
        );

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('guest/status')
                ->where(
                    'order.id',
                    $context['order']->id,
                )
                ->where(
                    'order.code',
                    $context['order']->code,
                )
                ->where(
                    'live.status',
                    'placed',
                )
                ->where(
                    'live.stage',
                    'placed',
                )
                ->where(
                    'live.paid',
                    false,
                )
                ->where(
                    'live.payment_method',
                    'cash',
                ),
        );
});

it('forbids another table session from viewing the order', function (): void {
    $context = createGuestOrderStatusContext();

    $otherTable = RestaurantTable::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'number' => '13',
    ]);

    $otherSession = TableSession::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'restaurant_table_id' => $otherTable->id,
        'guest_name' => 'Other Guest',
        'opened_at' => now(),
        'last_seen_at' => now(),
        'closed_at' => null,
    ]);

    $this
        ->withCookie(
            'qresto_table_session',
            $otherSession->token,
        )
        ->get(
            route(
                'guest.orders.status',
                $context['order'],
            ),
        )
        ->assertForbidden();
});

it('returns the recorded trail in chronological order', function (): void {
    $context = createGuestOrderStatusContext(
        OrderStatus::SERVED,
    );

    OrderEvent::factory()
        ->forOrder($context['order'])
        ->transition(
            OrderStatus::PENDING,
            OrderStatus::PREPARING,
        )
        ->create([
            'occurred_at' => Carbon::parse(
                '2026-10-04 18:05:00',
            ),
        ]);

    OrderEvent::factory()
        ->forOrder($context['order'])
        ->transition(
            OrderStatus::READY,
            OrderStatus::SERVED,
        )
        ->create([
            'occurred_at' => Carbon::parse(
                '2026-10-04 18:15:00',
            ),
        ]);

    OrderEvent::factory()
        ->forOrder($context['order'])
        ->transition(
            OrderStatus::PREPARING,
            OrderStatus::READY,
        )
        ->create([
            'occurred_at' => Carbon::parse(
                '2026-10-04 18:10:00',
            ),
        ]);

    $response = $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(
            route(
                'guest.orders.status',
                $context['order'],
            ),
        );

    $response->assertInertia(
        fn (Assert $page) => $page
            ->has('live.events', 4)
            ->where(
                'live.events.0.status',
                'placed',
            )
            ->where(
                'live.events.0.at',
                $context['placed_at']->toIso8601String(),
            )
            ->where(
                'live.events.1.status',
                'preparing',
            )
            ->where(
                'live.events.1.at',
                Carbon::parse('2026-10-04 18:05:00')
                    ->toIso8601String(),
            )
            ->where(
                'live.events.2.status',
                'ready',
            )
            ->where(
                'live.events.2.at',
                Carbon::parse('2026-10-04 18:10:00')
                    ->toIso8601String(),
            )
            ->where(
                'live.events.3.status',
                'served',
            )
            ->where(
                'live.events.3.at',
                Carbon::parse('2026-10-04 18:15:00')
                    ->toIso8601String(),
            ),
    );
});

it('returns paid card payment state', function (): void {
    $context = createGuestOrderStatusContext(
        OrderStatus::PAID,
    );

    Payment::factory()
        ->forOrder($context['order'])
        ->paid()
        ->create([
            'method' => 'card',
        ]);

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(
            route(
                'guest.orders.status',
                $context['order'],
            ),
        )
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('live.paid', true)
                ->where(
                    'live.payment_method',
                    'card',
                )
                ->where(
                    'live.stage',
                    'served',
                ),
        );
});

it('returns unpaid cash payment state', function (): void {
    $context = createGuestOrderStatusContext();

    Payment::factory()
        ->forOrder($context['order'])
        ->pending()
        ->create([
            'method' => 'cash',
        ]);

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(
            route(
                'guest.orders.status',
                $context['order'],
            ),
        )
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('live.paid', false)
                ->where(
                    'live.payment_method',
                    'cash',
                ),
        );
});

it('returns kitchen eta as a range', function (): void {
    $context = createGuestOrderStatusContext();

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(
            route(
                'guest.orders.status',
                $context['order'],
            ),
        )
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'live.eta_minutes.min',
                    10,
                )
                ->where(
                    'live.eta_minutes.max',
                    15,
                ),
        );
});

it('accounts for kitchen queue depth in the eta range', function (): void {
    $context = createGuestOrderStatusContext();

    Order::factory()
        ->withTable($context['table'])
        ->preparing()
        ->create([
            'restaurant_id' => $context['restaurant']->id,
            'placed_at' => $context['placed_at']
                ->copy()
                ->subMinute(),
        ]);

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(
            route(
                'guest.orders.status',
                $context['order'],
            ),
        )
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'live.eta_minutes.min',
                    12,
                )
                ->where(
                    'live.eta_minutes.max',
                    17,
                ),
        );
});

it('does not return eta once the order is served', function (): void {
    $context = createGuestOrderStatusContext(
        OrderStatus::SERVED,
    );

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get(
            route(
                'guest.orders.status',
                $context['order'],
            ),
        )
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'live.status',
                    'served',
                )
                ->where(
                    'live.eta_minutes',
                    null,
                ),
        );
});

it('reflects a staff status change on the next request', function (): void {
    $context = createGuestOrderStatusContext();

    $url = route(
        'guest.orders.status',
        $context['order'],
    );

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get($url)
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'live.stage',
                    'placed',
                ),
        );

    $context['order']->transitionTo(
        OrderStatus::PENDING,
        'staff',
    );

    $context['order']->transitionTo(
        OrderStatus::PREPARING,
        'staff',
    );

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get($url)
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'live.status',
                    'preparing',
                )
                ->where(
                    'live.stage',
                    'preparing',
                )
                ->where(
                    'live.events.1.status',
                    'preparing',
                ),
        );
});
it('reflects an assigned waiter change on the next request', function (): void {
    $context = createGuestOrderStatusContext();

    $firstWaiter = User::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'name' => 'Ahmad Saleh',
        'is_active' => true,
    ]);

    $secondWaiter = User::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'name' => 'Nadia Rahman',
        'is_active' => true,
    ]);

    $context['order']->update([
        'assigned_user_id' => $firstWaiter->id,
    ]);

    $url = route(
        'guest.orders.status',
        $context['order'],
    );

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get($url)
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'live.assigned_waiter.id',
                    $firstWaiter->id,
                )
                ->where(
                    'live.assigned_waiter.name',
                    'Ahmad Saleh',
                )
                ->where(
                    'live.assigned_waiter.initials',
                    'AS',
                ),
        );

    $context['order']->update([
        'assigned_user_id' => $secondWaiter->id,
    ]);

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->get($url)
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'live.assigned_waiter.id',
                    $secondWaiter->id,
                )
                ->where(
                    'live.assigned_waiter.name',
                    'Nadia Rahman',
                )
                ->where(
                    'live.assigned_waiter.initials',
                    'NR',
                ),
        );
});
