<?php

use App\CurrentRestaurant;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);
});

it('scopes every staff request to the signed-in person\'s restaurant', function () {
    $mine = Restaurant::factory()->create();
    $other = Restaurant::factory()->create();

    RestaurantTable::factory()->forRestaurant($mine)->count(2)->create();
    RestaurantTable::factory()->forRestaurant($other)->count(3)->create();
    Order::factory()->for($mine)->create();
    Order::factory()->for($other)->count(2)->create();

    $manager = User::factory()->create(['restaurant_id' => $mine->id]);
    $manager->assignRole('manager');

    $this->actingAs($manager)->get('/app/floor')->assertOk();

    expect(app(CurrentRestaurant::class)->id())->toBe($mine->id)
        ->and(RestaurantTable::query()->count())->toBe(2)
        ->and(Order::query()->count())->toBe(1);
});

it('makes another restaurant\'s order simply not found, even for a manager', function () {
    $mine = Restaurant::factory()->create();
    $theirOrder = Order::factory()->for(Restaurant::factory()->create())->preparing()->create();

    $manager = User::factory()->create(['restaurant_id' => $mine->id]);
    $manager->assignRole('manager');

    $this->actingAs($manager)
        ->patch("/app/orders/{$theirOrder->id}/advance")
        ->assertNotFound();
});

it('leaves people without a restaurant unscoped', function () {
    $user = User::factory()->create(['restaurant_id' => null]);

    $this->actingAs($user)->get('/settings/profile')->assertOk();

    expect(app(CurrentRestaurant::class)->id())->toBeNull();
});
