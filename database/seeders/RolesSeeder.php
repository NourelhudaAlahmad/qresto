<?php

namespace Database\Seeders;

use App\Enums\Capability;
use App\Enums\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [];

        foreach (Capability::cases() as $capability) {
            $permissions[$capability->value] = Permission::firstOrCreate([
                'name' => $capability->value,
                'guard_name' => 'web',
            ]);
        }

        $grants = [
            Role::WAITER->value => [
                Capability::SEE_OWN_TABLES->value,
                Capability::CHANGE_ORDER_STATUS->value,
                Capability::TAKE_PAYMENT->value,
            ],

            // No default grants specified in Issue #7.
            Role::RUNNER->value => [],

            // No default grants specified in Issue #7.
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

        foreach (Role::cases() as $roleEnum) {
            $role = SpatieRole::firstOrCreate([
                'name' => $roleEnum->value,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions(
                array_map(
                    fn (string $permission) => $permissions[$permission],
                    $grants[$roleEnum->value],
                ),
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
