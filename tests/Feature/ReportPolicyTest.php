<?php

namespace Tests\Feature;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\User;
use App\Policies\ReportPolicy;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_manager_can_view_and_export_reports(): void
    {
        $manager = User::factory()->create();

        $manager->assignRole(Role::MANAGER->value);

        $policy = new ReportPolicy;

        $this->assertTrue(
            $manager->can(
                Capability::FINANCIAL_REPORTS->value,
            ),
        );

        $this->assertTrue(
            $policy->viewAny($manager),
        );

        $this->assertTrue(
            $policy->export($manager),
        );
    }

    public function test_admin_can_view_and_export_reports(): void
    {
        $admin = User::factory()->create();

        $admin->assignRole(Role::ADMIN->value);

        $policy = new ReportPolicy;

        $this->assertTrue(
            $admin->can(
                Capability::FINANCIAL_REPORTS->value,
            ),
        );

        $this->assertTrue(
            $policy->viewAny($admin),
        );

        $this->assertTrue(
            $policy->export($admin),
        );
    }

    public function test_waiter_cannot_view_or_export_reports(): void
    {
        $waiter = User::factory()->create();

        $waiter->assignRole(Role::WAITER->value);

        $policy = new ReportPolicy;

        $this->assertFalse(
            $waiter->can(
                Capability::FINANCIAL_REPORTS->value,
            ),
        );

        $this->assertFalse(
            $policy->viewAny($waiter),
        );

        $this->assertFalse(
            $policy->export($waiter),
        );
    }

    public function test_kitchen_cannot_view_or_export_reports(): void
    {
        $kitchen = User::factory()->create();

        $kitchen->assignRole(Role::KITCHEN->value);

        $policy = new ReportPolicy;

        $this->assertFalse(
            $kitchen->can(
                Capability::FINANCIAL_REPORTS->value,
            ),
        );

        $this->assertFalse(
            $policy->viewAny($kitchen),
        );

        $this->assertFalse(
            $policy->export($kitchen),
        );
    }

    public function test_cashier_cannot_view_or_export_reports(): void
    {
        $cashier = User::factory()->create();

        $cashier->assignRole(Role::CASHIER->value);

        $policy = new ReportPolicy;

        $this->assertFalse(
            $cashier->can(
                Capability::FINANCIAL_REPORTS->value,
            ),
        );

        $this->assertFalse(
            $policy->viewAny($cashier),
        );

        $this->assertFalse(
            $policy->export($cashier),
        );
    }
}
