<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\User;
use App\Services\NavigationBuilder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_waiter_navigation_matches_the_capability_matrix(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Role::WAITER->value);

        $capabilities = [
            Capability::SEE_OWN_TABLES->value,
            Capability::CHANGE_ORDER_STATUS->value,
            Capability::TAKE_PAYMENT->value,
        ];

        $navigation = app(NavigationBuilder::class)->for(
            $user,
            $capabilities,
        );

        $this->assertSame(
            [
                'waiter',
                'floor',
                'auth',
            ],
            array_column($navigation, 'id'),
        );
    }

    public function test_kitchen_navigation_only_shows_order_items(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Role::KITCHEN->value);

        $capabilities = [
            Capability::SEE_ALL_ORDERS->value,
            Capability::CHANGE_ORDER_STATUS->value,
        ];

        $navigation = app(NavigationBuilder::class)->for(
            $user,
            $capabilities,
        );

        $this->assertSame(
            [
                'kds',
                'waiter',
            ],
            array_column($navigation, 'id'),
        );

        $this->assertNotContains(
            'reports',
            array_column($navigation, 'id'),
        );

        $this->assertNotContains(
            'menu',
            array_column($navigation, 'id'),
        );
    }

    public function test_manager_navigation_shows_manager_items(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Role::MANAGER->value);

        $capabilities = [
            Capability::SEE_OWN_TABLES->value,
            Capability::SEE_ALL_ORDERS->value,
            Capability::CHANGE_ORDER_STATUS->value,
            Capability::TAKE_PAYMENT->value,
            Capability::MANAGE_WAITER_ACCOUNTS->value,
            Capability::FINANCIAL_REPORTS->value,
        ];

        $navigation = app(NavigationBuilder::class)->for(
            $user,
            $capabilities,
        );

        $this->assertSame(
            [
                'manager',
                'waiter',
                'floor',
                'reports',
                'auth',
            ],
            array_column($navigation, 'id'),
        );
    }

    public function test_admin_navigation_shows_all_admin_sections(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Role::ADMIN->value);

        $capabilities = array_map(
            fn (Capability $capability) => $capability->value,
            Capability::cases(),
        );

        $navigation = app(NavigationBuilder::class)->for(
            $user,
            $capabilities,
        );

        $this->assertSame(
            [
                'admin',
                'menu',
                'reports',
                'manager',
                'auth',
            ],
            array_column($navigation, 'id'),
        );
    }

    public function test_runner_and_cashier_have_no_navigation_with_no_capabilities(): void
    {
        foreach ([Role::RUNNER, Role::CASHIER] as $role) {
            $user = User::factory()->create();

            $user->assignRole($role->value);

            $navigation = app(NavigationBuilder::class)->for(
                $user,
                [],
            );

            $this->assertSame([], $navigation);
        }
    }

    public function test_navigation_respects_the_capability_snapshot(): void
    {
        $user = User::factory()->create();

        $user->assignRole(Role::MANAGER->value);

        $snapshot = [
            Capability::SEE_ALL_ORDERS->value,
            Capability::FINANCIAL_REPORTS->value,
        ];

        $navigation = app(NavigationBuilder::class)->for(
            $user,
            $snapshot,
        );

        $this->assertSame(
            [
                'manager',
                'waiter',
                'reports',
                'auth',
            ],
            array_column($navigation, 'id'),
        );

        $this->assertNotContains(
            'floor',
            array_column($navigation, 'id'),
        );
    }
}
