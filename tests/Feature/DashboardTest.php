<?php

use App\Enums\Role;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use App\Support\LiveResponse;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Middleware;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesSeeder::class);
});

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));

    $response->assertOk();
});

it('allows a manager to load live orders', function (): void {
    $restaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $manager->assignRole(Role::MANAGER->value);

    Order::factory()->count(2)->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $response = $this
        ->actingAs($manager)
        ->get(route('dashboard'));

    $response->assertOk();
});

it('only returns orders from the current users restaurant', function (): void {
    $restaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $manager->assignRole(Role::MANAGER->value);

    $visibleOrder = Order::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    Order::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
    ]);

    $response = $this
        ->actingAs($manager)
        ->get(route('dashboard'));

    $response->assertOk();

    expect(
        collect($response->viewData('page')['props']['liveOrders']['items'])
            ->pluck('id')
            ->all(),
    )->toContain($visibleOrder->id);
});

it('allows a waiter to load only their assigned orders', function (): void {
    $restaurant = Restaurant::factory()->create();

    $waiter = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $waiter->assignRole(Role::WAITER->value);

    $ownOrder = Order::factory()->create([
        'restaurant_id' => $restaurant->id,
        'assigned_user_id' => $waiter->id,
    ]);

    Order::factory()->create([
        'restaurant_id' => $restaurant->id,
        'assigned_user_id' => null,
    ]);

    $response = $this
        ->actingAs($waiter)
        ->get(route('dashboard'));

    $response->assertOk();

    expect(
        collect($response->viewData('page')['props']['liveOrders']['items'])
            ->pluck('id')
            ->all(),
    )->toContain($ownOrder->id);
});

it('returns live orders as a dedicated prop for partial reloads', function (): void {
    $restaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $manager->assignRole(Role::MANAGER->value);

    Order::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $version = app(Middleware::class)->version(
        request(),
    );

    $response = $this
        ->actingAs($manager)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'dashboard',
            'X-Inertia-Partial-Data' => 'liveOrders',
            'X-Inertia-Version' => $version,
        ])
        ->get(route('dashboard'));

    $response->assertOk();
    $props = $response->json('props');

    expect(array_keys($props))
        ->toBe(['errors', 'liveOrders'])
        ->and($props['liveOrders'])
        ->toHaveKeys(['items', 'version']);
});

it('short-circuits when the live orders version has not changed', function (): void {
    $restaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $manager->assignRole(Role::MANAGER->value);

    Order::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $orders = Order::query()
        ->where('restaurant_id', $restaurant->id)
        ->get();

    $version = LiveResponse::version($orders);

    $response = $this
        ->actingAs($manager)
        ->withHeaders([
            'X-Inertia' => 'true',
            'X-Inertia-Partial-Component' => 'dashboard',
            'X-Inertia-Partial-Data' => 'liveOrders',
            'X-Inertia-Version' => app(Middleware::class)->version(
                request(),
            ),
        ])
        ->get(route('dashboard').'?version='.urlencode($version));

    $response->assertNoContent();
});
