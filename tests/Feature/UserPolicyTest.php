<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_manager_can_manage_staff_accounts_in_own_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $manager->assignRole(Role::MANAGER->value);

        $this->assertTrue(
            $manager->can(
                Capability::MANAGE_WAITER_ACCOUNTS->value,
            ),
        );

        $this->assertTrue(
            $manager->can('update', $staff),
        );
    }

    public function test_manager_cannot_manage_staff_from_another_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $manager = User::factory()->create([
            'restaurant_id' => $restaurantA->id,
        ]);

        $staff = User::factory()->create([
            'restaurant_id' => $restaurantB->id,
        ]);

        $manager->assignRole(Role::MANAGER->value);

        $this->assertFalse(
            $manager->can('update', $staff),
        );
    }

    public function test_waiter_cannot_manage_staff_accounts(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $waiter->assignRole(Role::WAITER->value);

        $this->assertFalse(
            $waiter->can(
                Capability::MANAGE_WAITER_ACCOUNTS->value,
            ),
        );

        $this->assertFalse(
            $waiter->can('update', $staff),
        );
    }

    public function test_kitchen_cannot_manage_staff_accounts(): void
    {
        $restaurant = Restaurant::factory()->create();

        $kitchen = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $kitchen->assignRole(Role::KITCHEN->value);

        $this->assertFalse(
            $kitchen->can(
                Capability::MANAGE_WAITER_ACCOUNTS->value,
            ),
        );

        $this->assertFalse(
            $kitchen->can('update', $staff),
        );
    }

    public function test_admin_can_manage_staff_accounts(): void
    {
        $restaurant = Restaurant::factory()->create();

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $staff = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $admin->assignRole(Role::ADMIN->value);

        $this->assertTrue(
            $admin->can(
                Capability::MANAGE_WAITER_ACCOUNTS->value,
            ),
        );

        $this->assertTrue(
            $admin->can('update', $staff),
        );
    }
}
