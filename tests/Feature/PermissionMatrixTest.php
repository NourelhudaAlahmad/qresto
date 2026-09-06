<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class PermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeded_permission_matrix_matches_design(): void
    {
        $this->seed(RolesSeeder::class);

        $expected = [
            Role::WAITER->value => [
                Capability::SEE_OWN_TABLES->value,
                Capability::CHANGE_ORDER_STATUS->value,
                Capability::TAKE_PAYMENT->value,
            ],
            Role::RUNNER->value => [],
            Role::CASHIER->value => [],
            Role::KITCHEN->value => [
                Capability::SEE_ALL_ORDERS->value,
                Capability::CHANGE_ORDER_STATUS->value,
            ],
            Role::MANAGER->value => [
                Capability::SEE_OWN_TABLES->value,
                Capability::SEE_ALL_ORDERS->value,
                Capability::CHANGE_ORDER_STATUS->value,
                Capability::TAKE_PAYMENT->value,
                Capability::MANAGE_WAITER_ACCOUNTS->value,
                Capability::FINANCIAL_REPORTS->value,
            ],
            Role::ADMIN->value => array_map(
                fn (Capability $capability) => $capability->value,
                Capability::cases(),
            ),
        ];

        foreach ($expected as $roleName => $permissionNames) {
            $role = SpatieRole::findByName($roleName, 'web');

            $actual = $role->permissions
                ->pluck('name')
                ->sort()
                ->values()
                ->all();

            sort($permissionNames);

            $this->assertSame(
                $permissionNames,
                $actual,
                "Permission matrix mismatch for role [{$roleName}].",
            );
        }

        $this->assertSame(6, SpatieRole::count());
        $this->assertSame(8, Permission::count());
    }
}
