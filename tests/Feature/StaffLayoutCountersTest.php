<?php

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

test('open orders count only active orders from the current restaurant', function () {
    $restaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $manager->assignRole('manager');

    Order::factory()
        ->for($restaurant)
        ->placed()
        ->create();

    Order::factory()
        ->for($restaurant)
        ->preparing()
        ->create();

    Order::factory()
        ->for($restaurant)
        ->paid()
        ->create();

    Order::factory()
        ->for($restaurant)
        ->cancelled()
        ->create();

    // An active order from another restaurant must not be counted.
    Order::factory()
        ->for($otherRestaurant)
        ->placed()
        ->create();

    $this->actingAs($manager)
        ->get('/manager/overview')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('staff/manager/overview')
                ->where('staffOpenOrders', 2)
        );
});

test('waiter cannot receive the restaurant wide order count', function () {
    $restaurant = Restaurant::factory()->create();

    $waiter = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $waiter->assignRole('waiter');

    Order::factory()
        ->for($restaurant)
        ->placed()
        ->create();

    $this->actingAs($waiter)
        ->get('/app/my-orders')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('staff/orders/kanban')
                ->where('staffOpenOrders', null)
        );
});

test('unread notifications count belongs to the current user only', function () {
    $restaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $otherUser = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $manager->assignRole('manager');

    // Two unread notifications for this manager.
    for ($i = 0; $i < 2; $i++) {
        $manager->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'staff-counter-test',
            'data' => [
                'message' => 'Test notification',
            ],
            'read_at' => null,
        ]);
    }

    // Read notification: must not be counted.
    $manager->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'staff-counter-test',
        'data' => [
            'message' => 'Read notification',
        ],
        'read_at' => now(),
    ]);

    // Another user's notification: must not be counted.
    $otherUser->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'staff-counter-test',
        'data' => [
            'message' => 'Other user notification',
        ],
        'read_at' => null,
    ]);

    $this->actingAs($manager)
        ->get('/manager/overview')
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('staff/manager/overview')
                ->where('staffUnreadNotifications', 2)
        );
});

test('open orders can refresh independently through an Inertia partial reload', function () {
    $restaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $manager->assignRole('manager');

    Order::factory()
        ->for($restaurant)
        ->placed()
        ->create();

    // Initial page: one active order.
    $response = $this->actingAs($manager)
        ->get('/manager/overview');

    $response->assertOk();

    // Another active order arrives after the initial response.
    Order::factory()
        ->for($restaurant)
        ->placed()
        ->create();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('staff/manager/overview')
            ->where('staffOpenOrders', 1)
            ->reloadOnly(
                'staffOpenOrders',
                fn (Assert $reload) => $reload
                    ->where('staffOpenOrders', 2)
                    ->missing('staffUnreadNotifications')
            )
    );
});

test('notifications can refresh independently through an Inertia partial reload', function () {
    $restaurant = Restaurant::factory()->create();

    $manager = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $manager->assignRole('manager');

    // Initial page: zero unread notifications.
    $response = $this->actingAs($manager)
        ->get('/manager/overview');

    $response->assertOk();

    // A new unread notification arrives.
    $manager->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => 'staff-counter-test',
        'data' => [
            'message' => 'New notification',
        ],
        'read_at' => null,
    ]);

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('staff/manager/overview')
            ->where('staffUnreadNotifications', 0)
            ->reloadOnly(
                'staffUnreadNotifications',
                fn (Assert $reload) => $reload
                    ->where('staffUnreadNotifications', 1)
                    ->missing('staffOpenOrders')
            )
    );
});
