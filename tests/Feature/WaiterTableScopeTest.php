<?php

use App\Enums\OrderStatus;
use App\Enums\TableState;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    $this->restaurant = Restaurant::factory()->create();

    $this->mine = RestaurantTable::factory()->forRestaurant($this->restaurant)
        ->create(['number' => '04', 'state' => TableState::SEATED, 'party_size' => 2, 'seated_at' => now()]);
    $this->theirs = RestaurantTable::factory()->forRestaurant($this->restaurant)
        ->create(['number' => '07', 'state' => TableState::SEATED, 'party_size' => 2, 'seated_at' => now()]);
    $this->free = RestaurantTable::factory()->forRestaurant($this->restaurant)
        ->create(['number' => '09', 'state' => TableState::FREE]);

    $this->nadia = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->nadia->assignRole('waiter');
    assignTonight($this->nadia, $this->mine);

    $this->lin = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $this->lin->assignRole('waiter');
    assignTonight($this->lin, $this->theirs);
});

it('shows a waiter only their own tables on "My tables"', function () {
    $this->actingAs($this->nadia)
        ->get('/app/floor')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('tables', 1)
            ->where('tables.0.number', '04'));
});

it('shows a manager the whole floor', function () {
    $sara = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $sara->assignRole('manager');

    $this->actingAs($sara)
        ->get('/app/floor')
        ->assertInertia(fn ($page) => $page->has('tables', 3));
});

it('refuses every floor action on a table that is someone else\'s tonight', function () {
    $order = Order::factory()->create([
        'restaurant_id' => $this->restaurant->id,
        'table_id' => $this->theirs->id,
        'status' => OrderStatus::SERVED,
        'is_paid' => false,
    ]);

    $this->actingAs($this->nadia)->getJson("/app/tables/{$this->theirs->id}")->assertForbidden();
    $this->actingAs($this->nadia)->post("/app/tables/{$this->theirs->id}/free")->assertForbidden();
    $this->actingAs($this->nadia)->post("/app/tables/{$this->free->id}/seat", ['party_size' => 2])->assertForbidden();
    $this->actingAs($this->nadia)
        ->post("/app/tables/{$this->theirs->id}/payments", [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount_minor' => 100,
        ])
        ->assertForbidden();
    $this->actingAs($this->nadia)
        ->get("/app/tables/{$this->theirs->id}/orders/{$order->id}/receipt")
        ->assertForbidden();

    expect($order->fresh()->is_paid)->toBeFalse()
        ->and($this->theirs->fresh()->state)->toBe(TableState::SEATED);
});

it('lets a waiter act on their own table', function () {
    $this->actingAs($this->nadia)->getJson("/app/tables/{$this->mine->id}")->assertOk();
});

it('stops access once the shift is over', function () {
    $this->travel(8)->hours();

    $this->actingAs($this->nadia)->getJson("/app/tables/{$this->mine->id}")->assertForbidden();
});
