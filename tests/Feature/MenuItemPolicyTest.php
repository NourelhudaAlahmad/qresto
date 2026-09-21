<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\MenuItem;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuItemPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_admin_can_manage_menu_items(): void
    {
        $restaurant = Restaurant::factory()->create();

        $admin = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $admin->assignRole(Role::ADMIN->value);

        $menuItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertTrue(
            $admin->can('update', $menuItem),
        );

        $this->assertTrue(
            $admin->can(
                Capability::EDIT_MENU_PRICES->value,
            ),
        );
    }

    public function test_waiter_cannot_manage_menu_items(): void
    {
        $restaurant = Restaurant::factory()->create();

        $waiter = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $waiter->assignRole(Role::WAITER->value);

        $menuItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertFalse(
            $waiter->can('update', $menuItem),
        );

        $this->assertFalse(
            $waiter->can(
                Capability::EDIT_MENU_PRICES->value,
            ),
        );
    }

    public function test_kitchen_cannot_manage_menu_items(): void
    {
        $restaurant = Restaurant::factory()->create();

        $kitchen = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $kitchen->assignRole(Role::KITCHEN->value);

        $menuItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $this->assertFalse(
            $kitchen->can('update', $menuItem),
        );

        $this->assertFalse(
            $kitchen->can(
                Capability::EDIT_MENU_PRICES->value,
            ),
        );
    }

    public function test_admin_cannot_manage_menu_item_from_another_restaurant(): void
    {
        $restaurantA = Restaurant::factory()->create();
        $restaurantB = Restaurant::factory()->create();

        $admin = User::factory()->create([
            'restaurant_id' => $restaurantA->id,
        ]);

        $admin->assignRole(Role::ADMIN->value);

        $menuItem = MenuItem::factory()->create([
            'restaurant_id' => $restaurantB->id,
        ]);

        $this->assertFalse(
            $admin->can('update', $menuItem),
        );
    }
}
