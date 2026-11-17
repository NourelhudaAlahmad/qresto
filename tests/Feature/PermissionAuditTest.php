<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\PermissionAudit;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PermissionManager;
use App\Services\RestaurantCapabilities;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class PermissionAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_grant_creates_permission_and_audit_record(): void
    {
        $this->seed(RolesSeeder::class);

        $restaurant = Restaurant::factory()->create();

        $actor = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $actor->assignRole(Role::ADMIN->value);

        app(PermissionManager::class)->grant(
            $actor,
            Role::MANAGER,
            Capability::EDIT_MENU_PRICES,
        );

        $this->assertTrue(
            app(RestaurantCapabilities::class)->roleHas(
                $restaurant->id,
                Role::MANAGER->value,
                Capability::EDIT_MENU_PRICES->value,
            ),
        );

        // The default grid is untouched.
        $this->assertFalse(
            SpatieRole::findByName(Role::MANAGER->value, 'web')
                ->hasPermissionTo(Capability::EDIT_MENU_PRICES->value),
        );

        $this->assertDatabaseHas('permission_audits', [
            'restaurant_id' => $restaurant->id,
            'actor_id' => $actor->id,
            'role' => Role::MANAGER->value,
            'permission' => Capability::EDIT_MENU_PRICES->value,
            'granted' => true,
        ]);
    }

    public function test_revoke_creates_permission_and_audit_record(): void
    {
        $this->seed(RolesSeeder::class);

        $restaurant = Restaurant::factory()->create();

        $actor = User::factory()->create([
            'restaurant_id' => $restaurant->id,
        ]);

        $actor->assignRole(Role::ADMIN->value);

        $permissionManager = app(PermissionManager::class);

        $permissionManager->grant(
            $actor,
            Role::MANAGER,
            Capability::EDIT_MENU_PRICES,
        );

        $permissionManager->revoke(
            $actor,
            Role::MANAGER,
            Capability::EDIT_MENU_PRICES,
        );

        $this->assertFalse(
            app(RestaurantCapabilities::class)->roleHas(
                $restaurant->id,
                Role::MANAGER->value,
                Capability::EDIT_MENU_PRICES->value,
            ),
        );

        $this->assertDatabaseHas('permission_audits', [
            'restaurant_id' => $restaurant->id,
            'actor_id' => $actor->id,
            'role' => Role::MANAGER->value,
            'permission' => Capability::EDIT_MENU_PRICES->value,
            'granted' => false,
        ]);

        $this->assertCount(2, PermissionAudit::all());

        // Back at the default: no override row left behind.
        $this->assertDatabaseCount('restaurant_role_capabilities', 0);
    }
}
