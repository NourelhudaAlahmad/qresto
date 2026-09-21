<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class OrderPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function seedRoles(): void
    {
        $this->seed(RolesSeeder::class);
    }

    public function test_waiter_cannot_view_another_waiters_order(): void
    {
        $this->seedRoles();

        $restaurant = Restaurant::factory()->create();

        $waiterA = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $waiterB = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $waiterRole = SpatieRole::findByName(
            Role::WAITER->value,
            'web',
        );

        $waiterA->assignRole($waiterRole);
        $waiterB->assignRole($waiterRole);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
            'assigned_user_id' => $waiterB->id,
        ]);

        $this->assertFalse(
            $waiterA->can(
                'view',
                $order,
            ),
        );
    }

    public function test_manager_can_view_any_order(): void
    {
        $this->seedRoles();

        $restaurant = Restaurant::factory()->create();

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $manager->assignRole(Role::MANAGER->value);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertTrue(
            $manager->can(
                'view',
                $order,
            ),
        );
    }

    public function test_kitchen_cannot_take_payment(): void
    {
        $this->seedRoles();

        $restaurant = Restaurant::factory()->create();

        $kitchen = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $kitchen->assignRole(Role::KITCHEN->value);

        $order = Order::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertFalse(
            $kitchen->can(
                'takePayment',
                $order,
            ),
        );
    }

    public function test_waiter_can_change_order_status(): void
    {
        $this->seedRoles();

        $waiter = User::factory()->create();

        $waiter->assignRole(Role::WAITER->value);

        $order = Order::factory()->create([
            'assigned_user_id' => $waiter->id,
        ]);

        $this->assertTrue(
            $waiter->can(
                'updateStatus',
                $order,
            ),
        );
    }

    public function test_waiter_can_take_payment(): void
    {
        $this->seedRoles();

        $waiter = User::factory()->create();

        $waiter->assignRole(Role::WAITER->value);

        $order = Order::factory()->create([
            'assigned_user_id' => $waiter->id,
        ]);

        $this->assertTrue(
            $waiter->can(
                'takePayment',
                $order,
            ),
        );
    }

    public function test_manager_can_see_all_orders_capability(): void
    {
        $this->seedRoles();

        $manager = User::factory()->create();

        $manager->assignRole(Role::MANAGER->value);

        $this->assertTrue(
            $manager->can(
                Capability::SEE_ALL_ORDERS->value,
            ),
        );
    }

    public function test_kitchen_does_not_have_payment_capability(): void
    {
        $this->seedRoles();

        $kitchen = User::factory()->create();

        $kitchen->assignRole(Role::KITCHEN->value);

        $this->assertFalse(
            $kitchen->can(
                Capability::TAKE_PAYMENT->value,
            ),
        );
    }
}
