<?php

use App\Models\User;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 * #70: /dashboard was the starter kit's page, in no role's navigation. It
 * now only sends a signed-in person to their own landing screen, and so
 * does opening /login while signed in.
 */

beforeEach(function (): void {
    $this->seed([RolesSeeder::class, AlBustanSeeder::class]);
});

function dashboardAccount(string $role): User
{
    return User::role($role)->orderBy('id')->firstOrFail();
}

/**
 * @return array<string, array{0: string, 1: string}>
 */
function roleLandings(): array
{
    return [
        'waiter' => ['waiter', 'app.my-orders'],
        'kitchen' => ['kitchen', 'app.pass'],
        'runner' => ['runner', 'app.pass'],
        'cashier' => ['cashier', 'app.orders'],
        'manager' => ['manager', 'manager.overview'],
        'admin' => ['admin', 'app.system'],
    ];
}

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

it('sends each role from /dashboard to its own landing screen', function (string $role, string $landing) {
    $this->actingAs(dashboardAccount($role))
        ->get('/dashboard')
        ->assertRedirect(route($landing, absolute: false));
})->with(roleLandings());

it('sends a signed-in person who opens /login to their landing screen', function (string $role, string $landing) {
    $this->actingAs(dashboardAccount($role))
        ->get('/login')
        ->assertRedirect(route($landing, absolute: false));
})->with(roleLandings());

it('lands every role on a screen that opens, not on another redirect', function (string $role, string $landing) {
    $this->actingAs(dashboardAccount($role))
        ->get(route($landing, absolute: false))
        ->assertOk();
})->with(roleLandings());

it('sends an account with no working screen to its own settings, never back to /dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertRedirect(route('profile.edit', absolute: false));
});

it('no longer renders the starter-kit dashboard page', function () {
    expect(file_exists(resource_path('js/pages/dashboard.tsx')))->toBeFalse()
        ->and(file_exists(resource_path('js/components/ui/placeholder-pattern.tsx')))->toBeFalse();
});
