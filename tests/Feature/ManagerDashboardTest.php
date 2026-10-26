<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    private function staff(
        Restaurant $restaurant,
        Role $role,
    ): User {
        $user = User::factory()->create([
            'restaurant_id' => $restaurant->id,
            'is_active' => true,
        ]);

        $user->assignRole($role->value);

        return $user;
    }

    public function test_manager_can_reassign_order_and_record_event(): void
    {
        $restaurant = Restaurant::factory()->create();

        $manager = $this->staff($restaurant, Role::MANAGER);
        $waiterA = $this->staff($restaurant, Role::WAITER);
        $waiterB = $this->staff($restaurant, Role::WAITER);

        $order = Order::factory()
            ->assignedTo($waiterA)
            ->pending()
            ->create();

        $this->actingAs($manager)
            ->patch("/app/orders/{$order->id}/assign", [
                'assigned_user_id' => $waiterB->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $waiterB->id,
            $order->fresh()->assigned_user_id,
        );

        $this->assertSame(
            OrderStatus::PENDING,
            $order->fresh()->status,
        );

        $this->assertSame(1, $order->events()->count());

        $event = $order->events()->firstOrFail();

        $this->assertSame($manager->id, $event->actor_id);
        $this->assertSame('staff', $event->actor_kind);
        $this->assertSame('pending', $event->from_status);
        $this->assertSame('pending', $event->to_status);

        $this->assertSame(
            'waiter_reassigned',
            $event->meta['action'],
        );

        $this->assertEquals(
            $waiterA->id,
            $event->meta['from_waiter_id'],
        );

        $this->assertEquals(
            $waiterB->id,
            $event->meta['to_waiter_id'],
        );
    }

    public function test_waiter_cannot_reassign_orders(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiterA = $this->staff($restaurant, Role::WAITER);
        $waiterB = $this->staff($restaurant, Role::WAITER);

        $order = Order::factory()
            ->assignedTo($waiterA)
            ->pending()
            ->create();

        $this->actingAs($waiterA)
            ->patch("/app/orders/{$order->id}/assign", [
                'assigned_user_id' => $waiterB->id,
            ])
            ->assertForbidden();

        $this->assertSame(
            $waiterA->id,
            $order->fresh()->assigned_user_id,
        );

        $this->assertSame(0, $order->events()->count());
    }

    public function test_manager_cannot_reassign_order_from_other_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();
        $otherRestaurant = Restaurant::factory()->create();

        $manager = $this->staff($restaurant, Role::MANAGER);
        $waiter = $this->staff($restaurant, Role::WAITER);

        $order = Order::factory()
            ->for($otherRestaurant)
            ->pending()
            ->create();

        $this->actingAs($manager)
            ->patch("/app/orders/{$order->id}/assign", [
                'assigned_user_id' => $waiter->id,
            ])
            ->assertNotFound();

        $this->assertNull($order->fresh()->assigned_user_id);
        $this->assertSame(0, $order->events()->count());
    }

    public function test_manager_cannot_assign_waiter_from_other_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();
        $otherRestaurant = Restaurant::factory()->create();

        $manager = $this->staff($restaurant, Role::MANAGER);
        $waiterA = $this->staff($restaurant, Role::WAITER);
        $waiterB = $this->staff($otherRestaurant, Role::WAITER);

        $order = Order::factory()
            ->assignedTo($waiterA)
            ->pending()
            ->create();

        $this->actingAs($manager)
            ->patch("/app/orders/{$order->id}/assign", [
                'assigned_user_id' => $waiterB->id,
            ])
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertSame(
            $waiterA->id,
            $order->fresh()->assigned_user_id,
        );

        $this->assertSame(0, $order->events()->count());
    }

    public function test_manager_cannot_assign_inactive_waiter(): void
    {
        $restaurant = Restaurant::factory()->create();

        $manager = $this->staff($restaurant, Role::MANAGER);
        $waiterA = $this->staff($restaurant, Role::WAITER);
        $waiterB = $this->staff($restaurant, Role::WAITER);

        $waiterB->update(['is_active' => false]);

        $order = Order::factory()
            ->assignedTo($waiterA)
            ->pending()
            ->create();

        $this->actingAs($manager)
            ->patch("/app/orders/{$order->id}/assign", [
                'assigned_user_id' => $waiterB->id,
            ])
            ->assertSessionHasErrors('assigned_user_id');

        $this->assertSame(
            $waiterA->id,
            $order->fresh()->assigned_user_id,
        );

        $this->assertSame(0, $order->events()->count());
    }

    public function test_manager_cannot_reassign_paid_or_cancelled_orders(): void
    {
        $restaurant = Restaurant::factory()->create();

        $manager = $this->staff($restaurant, Role::MANAGER);
        $waiterA = $this->staff($restaurant, Role::WAITER);
        $waiterB = $this->staff($restaurant, Role::WAITER);

        foreach (['paid', 'cancelled'] as $state) {
            $order = Order::factory()
                ->assignedTo($waiterA)
                ->{$state}()
                ->create();

            $this->actingAs($manager)
                ->patch("/app/orders/{$order->id}/assign", [
                    'assigned_user_id' => $waiterB->id,
                ])
                ->assertSessionHasErrors('order');

            $this->assertSame(
                $waiterA->id,
                $order->fresh()->assigned_user_id,
            );

            $this->assertSame(0, $order->events()->count());
        }
    }

    public function test_reassigning_to_same_waiter_does_not_create_event(): void
    {
        $restaurant = Restaurant::factory()->create();

        $manager = $this->staff($restaurant, Role::MANAGER);
        $waiter = $this->staff($restaurant, Role::WAITER);

        $order = Order::factory()
            ->assignedTo($waiter)
            ->pending()
            ->create();

        $this->actingAs($manager)
            ->patch("/app/orders/{$order->id}/assign", [
                'assigned_user_id' => $waiter->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $waiter->id,
            $order->fresh()->assigned_user_id,
        );

        $this->assertSame(0, $order->events()->count());
    }
}
