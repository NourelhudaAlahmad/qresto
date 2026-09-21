<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantTablePolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_waiter_can_view_tables_in_own_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $waiter->assignRole(Role::WAITER->value);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertTrue(
            $waiter->can('view', $table),
        );
    }

    public function test_waiter_cannot_view_tables_from_another_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $waiter = User::factory()->create([
            'restaurant_id' => $restaurantA->id,
        ]);

        $waiter->assignRole(Role::WAITER->value);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurantB->id,
        ]);

        $this->assertFalse(
            $waiter->can('view', $table),
        );
    }

    public function test_admin_can_manage_tables_in_own_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $admin->assignRole(Role::ADMIN->value);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertTrue(
            $admin->can('update', $table),
        );

        $this->assertTrue(
            $admin->can(
                Capability::MANAGE_RESTAURANT_CONFIG->value,
            ),
        );
    }

    public function test_waiter_cannot_manage_tables(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $waiter->assignRole(Role::WAITER->value);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertFalse(
            $waiter->can('update', $table),
        );

        $this->assertFalse(
            $waiter->can(
                Capability::MANAGE_RESTAURANT_CONFIG->value,
            ),
        );
    }

    public function test_admin_cannot_manage_table_from_another_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $admin = User::factory()->create([
            'restaurant_id' => $restaurantA->id,
        ]);

        $admin->assignRole(Role::ADMIN->value);

        $table = RestaurantTable::factory()->create([
            'restaurant_id' => $restaurantB->id,
        ]);

        $this->assertFalse(
            $admin->can('update', $table),
        );
    }
}
