<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\User;
use App\Services\CapabilitySnapshot;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CapabilitySnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_snapshot_stores_the_users_current_capabilities(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Role::WAITER->value);

        $snapshot = app(CapabilitySnapshot::class);

        $capabilities = $snapshot->store($user);

        $this->assertEqualsCanonicalizing(
            [
                Capability::SEE_OWN_TABLES->value,
                Capability::CHANGE_ORDER_STATUS->value,
                Capability::TAKE_PAYMENT->value,
            ],
            $capabilities,
        );

        $this->assertEqualsCanonicalizing(
            [
                Capability::SEE_OWN_TABLES->value,
                Capability::CHANGE_ORDER_STATUS->value,
                Capability::TAKE_PAYMENT->value,
            ],
            session(CapabilitySnapshot::SESSION_KEY),
        );
    }

    public function test_snapshot_does_not_change_when_database_permissions_change(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Role::WAITER->value);

        $snapshot = app(CapabilitySnapshot::class);

        $snapshot->store($user);

        $role = SpatieRole::findByName(
            Role::WAITER->value,
            'web',
        );

        $role->revokePermissionTo(
            Capability::TAKE_PAYMENT->value,
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $currentSnapshot = $snapshot->get($user);

        $this->assertContains(
            Capability::TAKE_PAYMENT->value,
            $currentSnapshot,
        );
    }

    public function test_snapshot_is_refreshed_when_store_is_called_again(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Role::WAITER->value);

        $snapshot = app(CapabilitySnapshot::class);

        $snapshot->store($user);

        $role = SpatieRole::findByName(
            Role::WAITER->value,
            'web',
        );

        $role->revokePermissionTo(
            Capability::TAKE_PAYMENT->value,
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user->refresh();

        $snapshot->store($user);

        $currentSnapshot = $snapshot->get($user);

        $this->assertNotContains(
            Capability::TAKE_PAYMENT->value,
            $currentSnapshot,
        );
    }

    public function test_login_automatically_creates_capability_snapshot(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $user->assignRole(Role::WAITER->value);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($user);

        $this->assertEqualsCanonicalizing(
            [
                Capability::SEE_OWN_TABLES->value,
                Capability::CHANGE_ORDER_STATUS->value,
                Capability::TAKE_PAYMENT->value,
            ],
            session(CapabilitySnapshot::SESSION_KEY),
        );
    }
}
