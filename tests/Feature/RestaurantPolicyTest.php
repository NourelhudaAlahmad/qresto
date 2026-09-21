<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\User;
use App\Policies\RestaurantPolicy;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_admin_can_manage_own_restaurant(): void
    {
        $restaurant = Restaurant::factory()->create();

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $admin->assignRole(Role::ADMIN->value);

        $policy = new RestaurantPolicy;

        $this->assertTrue(
            $admin->can(
                Capability::MANAGE_RESTAURANT_CONFIG->value,
            ),
        );

        $this->assertTrue(
            $policy->view($admin, $restaurant),
        );

        $this->assertTrue(
            $policy->update($admin, $restaurant),
        );

        $this->assertTrue(
            $policy->delete($admin, $restaurant),
        );
    }

    public function test_admin_cannot_manage_another_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $admin = User::factory()->create([
            'restaurant_id' => $restaurantA->id,
        ]);

        $admin->assignRole(Role::ADMIN->value);

        $policy = new RestaurantPolicy;

        $this->assertFalse(
            $policy->update($admin, $restaurantB),
        );

        $this->assertFalse(
            $policy->delete($admin, $restaurantB),
        );
    }

    public function test_waiter_cannot_manage_restaurant_configuration(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $waiter->assignRole(Role::WAITER->value);

        $policy = new RestaurantPolicy;

        $this->assertFalse(
            $waiter->can(
                Capability::MANAGE_RESTAURANT_CONFIG->value,
            ),
        );

        $this->assertFalse(
            $policy->update($waiter, $restaurant),
        );

        $this->assertFalse(
            $policy->delete($waiter, $restaurant),
        );
    }

    public function test_manager_cannot_manage_restaurant_configuration(): void
    {
        $restaurant = Restaurant::factory()->create();

        $manager = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $manager->assignRole(Role::MANAGER->value);

        $policy = new RestaurantPolicy;

        $this->assertFalse(
            $manager->can(
                Capability::MANAGE_RESTAURANT_CONFIG->value,
            ),
        );

        $this->assertFalse(
            $policy->update($manager, $restaurant),
        );

        $this->assertFalse(
            $policy->delete($manager, $restaurant),
        );
    }

    public function test_user_can_only_view_own_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $user = User::factory()->create([
            'restaurant_id' => $restaurantA->id,
        ]);

        $user->assignRole(Role::WAITER->value);

        $policy = new RestaurantPolicy;

        $this->assertTrue(
            $policy->view($user, $restaurantA),
        );

        $this->assertFalse(
            $policy->view($user, $restaurantB),
        );
    }
}
