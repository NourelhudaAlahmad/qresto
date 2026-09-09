<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\OrderLine;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_transition_updates_status_and_creates_one_event(): void
    {
        $order = Order::factory()->placed()->create();

        $user = User::factory()->create([
            'restaurant_id' => $order->restaurant_id,
        ]);

        $order->transitionTo(OrderStatus::PENDING, $user);

        $order->refresh();

        $this->assertSame(
            OrderStatus::PENDING,
            $order->status,
        );

        $this->assertDatabaseCount('order_events', 1);

        $event = OrderEvent::first();

        $this->assertSame(
            $order->id,
            $event->order_id,
        );

        $this->assertSame(
            OrderStatus::PLACED->value,
            $event->from_status,
        );

        $this->assertSame(
            OrderStatus::PENDING->value,
            $event->to_status,
        );

        $this->assertSame(
            $user->id,
            $event->actor_id,
        );

        $this->assertSame(
            'staff',
            $event->actor_kind,
        );
    }

    public function test_system_transition_creates_event_with_system_actor(): void
    {
        $order = Order::factory()->placed()->create();

        $order->transitionTo(OrderStatus::PENDING);

        $event = OrderEvent::first();

        $this->assertSame(
            'system',
            $event->actor_kind,
        );

        $this->assertNull($event->actor_id);
    }

    public function test_guest_transition_creates_event_with_guest_actor(): void
    {
        $order = Order::factory()->placed()->create();

        $order->transitionTo(
            OrderStatus::PENDING,
            'guest',
        );

        $event = OrderEvent::first();

        $this->assertSame(
            'guest',
            $event->actor_kind,
        );

        $this->assertNull($event->actor_id);
    }

    public function test_illegal_transition_throws_exception(): void
    {
        $order = Order::factory()->served()->create();

        $this->expectException(\RuntimeException::class);

        $order->transitionTo(OrderStatus::PENDING);
    }

    public function test_illegal_transition_does_not_create_event(): void
    {
        $order = Order::factory()->served()->create();

        try {
            $order->transitionTo(OrderStatus::PENDING);
        } catch (\RuntimeException) {
            // Expected exception.
        }

        $this->assertDatabaseCount('order_events', 0);
    }

    public function test_order_cannot_transition_to_same_status(): void
    {
        $order = Order::factory()->pending()->create();

        $this->expectException(\RuntimeException::class);

        $order->transitionTo(OrderStatus::PENDING);
    }

    public function test_full_legal_order_lifecycle_creates_event_for_each_transition(): void
    {
        $order = Order::factory()->placed()->create();

        $order->transitionTo(OrderStatus::PENDING);
        $order->transitionTo(OrderStatus::PREPARING);
        $order->transitionTo(OrderStatus::READY);
        $order->transitionTo(OrderStatus::SERVED);
        $order->transitionTo(OrderStatus::PAID);

        $order->refresh();

        $this->assertSame(
            OrderStatus::PAID,
            $order->status,
        );

        $this->assertDatabaseCount('order_events', 5);

        $statuses = $order->events
            ->pluck('to_status')
            ->all();

        $this->assertSame(
            [
                'pending',
                'preparing',
                'ready',
                'served',
                'paid',
            ],
            $statuses,
        );
    }

    public function test_deleting_menu_item_keeps_historical_order_line_snapshot(): void
    {
        $order = Order::factory()->create();

        $menuItem = MenuItem::factory()->create([
            'restaurant_id' => $order->restaurant_id,
            'name' => 'Kofta',
            'price' => '19.50',
        ]);

        $line = OrderLine::factory()
            ->forOrder($order)
            ->forMenuItem($menuItem)
            ->create([
                'name_snapshot' => 'Kofta',
                'unit_price' => '19.50',
                'qty' => 2,
                'line_total' => '39.00',
            ]);

        $menuItemId = $menuItem->id;

        $menuItem->delete();

        $line->refresh();

        $this->assertNull(
            $line->menu_item_id,
        );

        $this->assertSame(
            'Kofta',
            $line->name_snapshot,
        );

        $this->assertSame(
            1950,
            $line->unit_price->amount(),
        );

        $this->assertSame(
            2,
            $line->qty,
        );

        $this->assertSame(
            3900,
            $line->line_total->amount(),
        );

        $this->assertDatabaseMissing('menu_items', [
            'id' => $menuItemId,
        ]);
    }

    public function test_order_code_must_be_unique_within_same_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'code' => '#A-1043',
        ]);

        $this->expectException(QueryException::class);

        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'code' => '#A-1043',
        ]);
    }

    public function test_same_order_code_is_allowed_for_different_restaurants(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $orderA = Order::factory()->create([
            'restaurant_id' => $restaurantA->id,
            'code' => '#A-1043',
        ]);

        $orderB = Order::factory()->create([
            'restaurant_id' => $restaurantB->id,
            'code' => '#A-1043',
        ]);

        $this->assertSame(
            '#A-1043',
            $orderA->code,
        );

        $this->assertSame(
            '#A-1043',
            $orderB->code,
        );

        $this->assertNotSame(
            $orderA->restaurant_id,
            $orderB->restaurant_id,
        );
    }

    public function test_active_scope_excludes_paid_and_cancelled_orders(): void
    {
        Order::factory()->pending()->create();
        Order::factory()->preparing()->create();
        Order::factory()->ready()->create();
        Order::factory()->served()->create();

        Order::factory()->paid()->create();
        Order::factory()->cancelled()->create();

        $activeOrders = Order::active()->get();

        $this->assertCount(
            4,
            $activeOrders,
        );

        $this->assertTrue(
            $activeOrders->every(
                fn (Order $order) => ! in_array(
                    $order->status,
                    [
                        OrderStatus::PAID,
                        OrderStatus::CANCELLED,
                    ],
                    true,
                ),
            ),
        );
    }

    public function test_unpaid_scope_returns_only_unpaid_orders(): void
    {
        Order::factory()->unpaid()->create();
        Order::factory()->paid()->create();

        $unpaidOrders = Order::unpaid()->get();

        $this->assertCount(
            1,
            $unpaidOrders,
        );

        $this->assertFalse(
            $unpaidOrders->first()->is_paid,
        );
    }

    public function test_for_waiter_scope_returns_orders_assigned_to_user(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $otherWaiter = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'assigned_user_id' => $waiter->id,
        ]);

        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'assigned_user_id' => $otherWaiter->id,
        ]);

        Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'assigned_user_id' => null,
        ]);

        $orders = Order::forWaiter($waiter)->get();

        $this->assertCount(
            1,
            $orders,
        );

        $this->assertSame(
            $waiter->id,
            $orders->first()->assigned_user_id,
        );
    }

    public function test_elapsed_minutes_returns_minutes_since_order_was_placed(): void
    {
        $order = Order::factory()->create([
            'placed_at' => now()->subMinutes(15),
        ]);

        $this->assertSame(
            15,
            $order->elapsed_minutes,
        );
    }
}
