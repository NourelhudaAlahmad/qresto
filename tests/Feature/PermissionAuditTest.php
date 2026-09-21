<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\PermissionAudit;
use App\Models\Restaurant;
use App\Models\User;
use App\Services\PermissionManager;
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

        $role = SpatieRole::findByName(
            Role::MANAGER->value,
            'web',
        );

        $permissionManager = app(PermissionManager::class);

        $permissionManager->grant(
            $actor,
            $role,
            Capability::EDIT_MENU_PRICES,
        );

        $this->assertTrue(
            $role->fresh()->hasPermissionTo(
                Capability::EDIT_MENU_PRICES->value,
            ),
        );

        $this->assertDatabaseHas('permission_audits', [
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

        $role = SpatieRole::findByName(
            Role::MANAGER->value,
            'web',
        );

        $permissionManager = app(PermissionManager::class);

        $permissionManager->grant(
            $actor,
            $role,
            Capability::EDIT_MENU_PRICES,
        );

        $permissionManager->revoke(
            $actor,
            $role,
            Capability::EDIT_MENU_PRICES,
        );

        $this->assertFalse(
            $role->fresh()->hasPermissionTo(
                Capability::EDIT_MENU_PRICES->value,
            ),
        );

        $this->assertDatabaseHas('permission_audits', [
            'actor_id' => $actor->id,
            'role' => Role::MANAGER->value,
            'permission' => Capability::EDIT_MENU_PRICES->value,
            'granted' => false,
        ]);

        $this->assertCount(2, PermissionAudit::all());
    }
}
