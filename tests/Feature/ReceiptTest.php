<?php

use App\Enums\OrderStatus;
use App\Enums\TableState;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('see_own_tables', 'web');

    $waiter = Role::findOrCreate('waiter', 'web');
    $waiter->givePermissionTo('see_own_tables');
});

function receiptFixtures(): array
{
    $restaurant = Restaurant::factory()->create();

    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);
    $user->assignRole('waiter');

    $table = RestaurantTable::factory()
        ->forRestaurant($restaurant)
        ->create([
            'state' => TableState::SEATED,
            'party_size' => 2,
        ]);

    assignTonight($user, $table);

    $order = Order::factory()->create([
        'restaurant_id' => $restaurant->id,
        'table_id' => $table->id,
        'status' => OrderStatus::SERVED,
        'total' => 3431,
        'currency' => 'USD',
        'is_paid' => false,
        'paid_at' => null,
    ]);

    return [$restaurant, $user, $table, $order];
}

it('redirects guests to login before viewing a receipt', function (): void {
    [,, $table, $order] = receiptFixtures();

    $this->get("/app/tables/{$table->id}/orders/{$order->id}/receipt")
        ->assertRedirect(route('login'));
});

it('renders the receipt for an authorized waiter', function (): void {
    [, $user, $table, $order] = receiptFixtures();

    $this->actingAs($user)
        ->get("/app/tables/{$table->id}/orders/{$order->id}/receipt")
        ->assertOk()
        ->assertViewIs('receipts.order')
        ->assertViewHas('totalMinor', 3431)
        ->assertViewHas('paidMinor', 0)
        ->assertViewHas('balanceMinor', 3431);
});

it('calculates the balance after a partial cash payment', function (): void {
    [, $user, $table, $order] = receiptFixtures();

    Payment::factory()
        ->forOrder($order)
        ->create([
            'method' => 'cash',
            'status' => 'paid',
            'amount' => 1000,
        ]);

    $this->actingAs($user)
        ->get("/app/tables/{$table->id}/orders/{$order->id}/receipt")
        ->assertOk()
        ->assertViewHas('paidMinor', 1000)
        ->assertViewHas('balanceMinor', 2431);
});

it('calculates a fully paid receipt with cash and POS payments', function (): void {
    [, $user, $table, $order] = receiptFixtures();

    Payment::factory()->forOrder($order)->create([
        'method' => 'cash',
        'status' => 'paid',
        'amount' => 1000,
    ]);

    Payment::factory()->forOrder($order)->create([
        'method' => 'pos',
        'status' => 'paid',
        'amount' => 2431,
    ]);

    $order->update([
        'status' => OrderStatus::PAID,
        'is_paid' => true,
        'paid_at' => now(),
    ]);

    $this->actingAs($user)
        ->get("/app/tables/{$table->id}/orders/{$order->id}/receipt")
        ->assertOk()
        ->assertViewHas('paidMinor', 3431)
        ->assertViewHas('balanceMinor', 0)
        ->assertViewHas('payments', function ($payments): bool {
            return $payments->count() === 2
                && $payments->pluck('method')->all() === ['cash', 'pos'];
        });
});

it('does not count pending payments as received', function (): void {
    [, $user, $table, $order] = receiptFixtures();

    Payment::factory()->forOrder($order)->create([
        'method' => 'cash',
        'status' => 'pending',
        'amount' => 3431,
    ]);

    $this->actingAs($user)
        ->get("/app/tables/{$table->id}/orders/{$order->id}/receipt")
        ->assertOk()
        ->assertViewHas('paidMinor', 0)
        ->assertViewHas('balanceMinor', 3431);
});

it('hides a receipt from another restaurant', function (): void {
    [,, $table, $order] = receiptFixtures();

    $otherRestaurant = Restaurant::factory()->create();

    $otherUser = User::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
    ]);
    $otherUser->assignRole('waiter');

    $this->actingAs($otherUser)
        ->get("/app/tables/{$table->id}/orders/{$order->id}/receipt")
        ->assertNotFound();
});

it('returns not found when the order belongs to a different table', function (): void {
    [$restaurant, $user,, $order] = receiptFixtures();

    $otherTable = RestaurantTable::factory()
        ->forRestaurant($restaurant)
        ->create();

    assignTonight($user, $otherTable);

    $this->actingAs($user)
        ->get("/app/tables/{$otherTable->id}/orders/{$order->id}/receipt")
        ->assertNotFound();
});

it('returns not found when the order belongs to another restaurant', function (): void {
    [, $user, $table] = receiptFixtures();

    $otherRestaurant = Restaurant::factory()->create();

    $otherOrder = Order::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
        'table_id' => null,
    ]);

    $this->actingAs($user)
        ->get("/app/tables/{$table->id}/orders/{$otherOrder->id}/receipt")
        ->assertNotFound();
});
