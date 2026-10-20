<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\KitchenOrderVoided;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WaiterOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function staff(
        Restaurant $restaurant,
        Role $role
    ): User {
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    public function test_waiter_sees_only_assigned_orders(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $anotherWaiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $mine = Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        Order::factory()
            ->assignedTo($anotherWaiter)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->get('/app/my-orders')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('staff/orders/kanban')
                    ->has('orders', 1)
                    ->where('orders.0.id', $mine->id)
                    ->where('orders.0.status', 'pending')
                    ->etc()
            );
    }

    public function test_manager_sees_all_orders_in_own_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $otherRestaurant = Restaurant::factory()->create();

        $manager = $this->staff(
            $restaurant,
            Role::MANAGER
        );

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        Order::factory()
            ->for($restaurant)
            ->preparing()
            ->create();

        Order::factory()
            ->for($otherRestaurant)
            ->pending()
            ->create();

        $this->actingAs($manager)
            ->get('/app/orders')
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('staff/orders/kanban')
                    ->has('orders', 2)
                    ->etc()
            );
    }

    public function test_waiter_cannot_open_manager_orders_page(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $this->actingAs($waiter)
            ->get('/app/orders')
            ->assertForbidden();
    }

    public function test_advance_changes_one_status_and_records_one_event(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $order = Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->patch("/app/orders/{$order->id}/advance")
            ->assertRedirect();

        $order->refresh();

        $this->assertSame(
            OrderStatus::PREPARING,
            $order->status
        );

        $this->assertSame(
            1,
            $order->events()->count()
        );

        $event = $order->events()->firstOrFail();

        $this->assertSame(
            'pending',
            $event->from_status
        );

        $this->assertSame(
            'preparing',
            $event->to_status
        );

        $this->assertSame(
            $waiter->id,
            $event->actor_id
        );

        $this->assertSame(
            'staff',
            $event->actor_kind
        );
    }

    public function test_placed_order_advances_to_pending_without_skipping(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $order = Order::factory()
            ->assignedTo($waiter)
            ->placed()
            ->create();

        $this->actingAs($waiter)
            ->patch("/app/orders/{$order->id}/advance")
            ->assertRedirect();

        $this->assertSame(
            OrderStatus::PENDING,
            $order->fresh()->status
        );

        $this->assertSame(
            1,
            $order->events()->count()
        );
    }

    public function test_waiter_cannot_advance_another_waiters_order(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $anotherWaiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $order = Order::factory()
            ->assignedTo($anotherWaiter)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->patch("/app/orders/{$order->id}/advance")
            ->assertForbidden();

        $this->assertSame(
            OrderStatus::PENDING,
            $order->fresh()->status
        );

        $this->assertSame(
            0,
            $order->events()->count()
        );
    }

    public function test_waiter_cannot_advance_order_from_another_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $otherRestaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $order = Order::factory()
            ->for($otherRestaurant)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->patch("/app/orders/{$order->id}/advance")
            ->assertNotFound();

        $this->assertSame(
            OrderStatus::PENDING,
            $order->fresh()->status
        );
    }

    public function test_void_requires_a_reason(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $order = Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->post("/app/orders/{$order->id}/void", [
                'reason' => '',
            ])
            ->assertSessionHasErrors('reason');

        $this->assertSame(
            OrderStatus::PENDING,
            $order->fresh()->status
        );

        $this->assertSame(
            0,
            $order->events()->count()
        );
    }

    public function test_void_records_reason_and_actor(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $order = Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        $reason = 'Guest requested cancellation';

        $this->actingAs($waiter)
            ->post("/app/orders/{$order->id}/void", [
                'reason' => $reason,
            ])
            ->assertRedirect();

        $order->refresh();

        $this->assertSame(
            OrderStatus::CANCELLED,
            $order->status
        );

        $this->assertSame(
            $reason,
            $order->void_reason
        );

        $this->assertSame(
            $waiter->id,
            $order->voided_by
        );

        $this->assertNotNull(
            $order->voided_at
        );

        $this->assertSame(
            1,
            $order->events()->count()
        );

        $event = $order->events()->firstOrFail();

        $this->assertSame(
            $waiter->id,
            $event->actor_id
        );

        $this->assertSame(
            'cancelled',
            $event->to_status
        );

        $this->assertSame(
            $reason,
            $event->meta['reason'] ?? null
        );
    }

    public function test_waiter_cannot_void_another_waiters_order(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $anotherWaiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $order = Order::factory()
            ->assignedTo($anotherWaiter)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->post("/app/orders/{$order->id}/void", [
                'reason' => 'Guest changed mind',
            ])
            ->assertForbidden();

        $this->assertSame(
            OrderStatus::PENDING,
            $order->fresh()->status
        );

        $this->assertSame(
            0,
            $order->events()->count()
        );
    }

    public function test_new_order_appears_on_next_partial_reload(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER
        );

        $first = Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->get('/app/my-orders')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('staff/orders/kanban')
                    ->has('orders', 1)
                    ->where('orders.0.id', $first->id)
                    ->etc()
            );

        Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->get('/app/my-orders')
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('staff/orders/kanban')
                    ->reloadOnly(
                        'orders',
                        fn (Assert $reload) => $reload
                            ->has('orders', 2)
                    )
            );
    }

    public function test_void_notifies_only_active_kitchen_staff_in_same_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();
        $otherRestaurant = Restaurant::factory()->create();

        $waiter = $this->staff(
            $restaurant,
            Role::WAITER,
        );

        $kitchen = $this->staff(
            $restaurant,
            Role::KITCHEN,
        );

        $otherKitchen = $this->staff(
            $otherRestaurant,
            Role::KITCHEN,
        );

        $inactiveKitchen = $this->staff(
            $restaurant,
            Role::KITCHEN,
        );

        $inactiveKitchen->update([
            'is_active' => false,
        ]);

        $order = Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        $this->actingAs($waiter)
            ->post("/app/orders/{$order->id}/void", [
                'reason' => 'Guest cancelled order',
            ])
            ->assertRedirect();

        $notification = $kitchen
            ->notifications()
            ->firstOrFail();

        $this->assertSame(
            KitchenOrderVoided::class,
            $notification->type,
        );

        $this->assertSame(
            'order_voided',
            $notification->data['type'],
        );

        $this->assertSame(
            $order->id,
            $notification->data['order_id'],
        );

        $this->assertSame(
            1,
            $kitchen->notifications()->count(),
        );

        $this->assertSame(
            0,
            $otherKitchen->notifications()->count(),
        );

        $this->assertSame(
            0,
            $inactiveKitchen->notifications()->count(),
        );

        $this->assertSame(
            1,
            $order->events()->count(),
        );
    }
}
