<?php

use App\Enums\OrderStatus;
use App\Enums\TableState;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('see_own_tables', 'web');
    Permission::findOrCreate('take_payment', 'web');

    $waiter = Role::findOrCreate('waiter', 'web');

    $waiter->syncPermissions([
        'see_own_tables',
        'take_payment',
    ]);
});

function floorTableWithUser(): array
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
            'seated_at' => now(),
        ]);

    assignTonight($user, $table);

    return [$user, $table];
}

function floorOrderWithPayment(
    RestaurantTable $table,
    string $method = 'cash',
): array {
    $order = Order::factory()->create([
        'restaurant_id' => $table->restaurant_id,
        'table_id' => $table->id,
        'status' => OrderStatus::SERVED,
        'is_paid' => false,
        'paid_at' => null,
        'total' => 9220,
        'currency' => 'TRY',
    ]);

    $payment = Payment::factory()
        ->forOrder($order)
        ->pending()
        ->create([
            'method' => $method,
            'amount' => 9220,
            'taken_by' => null,
            'paid_at' => null,
        ]);

    return [$order, $payment];
}

// ============================================================
// FREE TABLE TESTS
// ============================================================

it('Floor refuses to free a table with an unpaid order', function (): void {
    [$user, $table] = floorTableWithUser();

    Order::factory()->create([
        'restaurant_id' => $table->restaurant_id,
        'table_id' => $table->id,
        'status' => OrderStatus::SERVED,
        'is_paid' => false,
    ]);

    $this->actingAs($user)
        ->from('/app/floor')
        ->post("/app/tables/{$table->id}/free")
        ->assertRedirect('/app/floor')
        ->assertSessionHasErrors('table');

    expect($table->fresh()->state)
        ->toBe(TableState::SEATED);
});

it('Floor frees a table after its bill is paid', function (): void {
    [$user, $table] = floorTableWithUser();

    Order::factory()->create([
        'restaurant_id' => $table->restaurant_id,
        'table_id' => $table->id,
        'status' => OrderStatus::PAID,
        'is_paid' => true,
    ]);

    $this->actingAs($user)
        ->post("/app/tables/{$table->id}/free")
        ->assertRedirect();

    expect($table->fresh()->state)->toBe(TableState::FREE)
        ->and($table->fresh()->party_size)->toBe(0)
        ->and($table->fresh()->seated_at)->toBeNull();
});

it('Floor closes the open table session when freeing', function (): void {
    [$user, $table] = floorTableWithUser();

    $session = TableSession::factory()
        ->forTable($table)
        ->active()
        ->create();

    $this->actingAs($user)
        ->post("/app/tables/{$table->id}/free")
        ->assertRedirect();

    expect($session->fresh()->closed_at)->not->toBeNull()
        ->and($table->fresh()->state)->toBe(TableState::FREE);
});

it('Floor hides a table from another restaurant when freeing it', function (): void {
    [$user] = floorTableWithUser();

    $otherRestaurant = Restaurant::factory()->create();

    $otherTable = RestaurantTable::factory()
        ->forRestaurant($otherRestaurant)
        ->create([
            'state' => TableState::SEATED,
        ]);

    $this->actingAs($user)
        ->post("/app/tables/{$otherTable->id}/free")
        ->assertNotFound();

    expect($otherTable->fresh()->state)
        ->toBe(TableState::SEATED);
});

// ============================================================
// CASH AND POS PAYMENT TESTS
// ============================================================

it('Floor settles a cash payment and records the staff member', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $payment] = floorOrderWithPayment($table, 'cash');

    $this->actingAs($user)
        ->post("/app/tables/{$table->id}/payments", [
            'order_id' => $order->id,
            'method' => 'cash',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $payment->refresh();
    $order->refresh();

    expect($payment->status)->toBe('paid')
        ->and($payment->taken_by)->toBe($user->id)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($order->is_paid)->toBeTrue()
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::PAID);

    $this->assertDatabaseHas('order_events', [
        'order_id' => $order->id,
        'to_status' => OrderStatus::PAID->value,
        'actor_id' => $user->id,
    ]);
});

it('Floor settles a POS payment', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $payment] = floorOrderWithPayment($table, 'pos');

    $this->actingAs($user)
        ->post("/app/tables/{$table->id}/payments", [
            'order_id' => $order->id,
            'method' => 'pos',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->taken_by)->toBe($user->id)
        ->and($order->fresh()->is_paid)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::PAID);
});

it('Floor refuses to settle an order twice', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $payment] = floorOrderWithPayment($table);

    $url = "/app/tables/{$table->id}/payments";

    $this->actingAs($user)
        ->post($url, [
            'order_id' => $order->id,
            'method' => 'cash',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post($url, [
            'order_id' => $order->id,
            'method' => 'cash',
        ])
        ->assertSessionHasErrors('payment');

    expect($payment->fresh()->status)->toBe('paid');

    $this->assertDatabaseCount('payments', 1);

    expect(
        $order->events()
            ->where('to_status', OrderStatus::PAID->value)
            ->count(),
    )->toBe(1);
});

it('Floor denies payment without the take_payment permission', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $payment] = floorOrderWithPayment($table);

    $user->revokePermissionTo('take_payment');

    // The waiter role grants this permission.
    // Remove it from the role for this test.
    Role::findByName('waiter', 'web')
        ->revokePermissionTo('take_payment');

    app(PermissionRegistrar::class)
        ->forgetCachedPermissions();

    $this->actingAs($user)
        ->post("/app/tables/{$table->id}/payments", [
            'order_id' => $order->id,
            'method' => 'cash',
        ])
        ->assertForbidden();

    expect($payment->fresh()->status)->toBe('pending')
        ->and($order->fresh()->is_paid)->toBeFalse();
});

it('Floor refuses payment for an order belonging to another table', function (): void {
    [$user, $table] = floorTableWithUser();

    $otherTable = RestaurantTable::factory()
        ->forRestaurant($table->restaurant)
        ->create([
            'state' => TableState::SEATED,
            'party_size' => 2,
        ]);

    [$otherOrder, $otherPayment] = floorOrderWithPayment($otherTable);

    $this->actingAs($user)
        ->post("/app/tables/{$table->id}/payments", [
            'order_id' => $otherOrder->id,
            'method' => 'cash',
        ])
        ->assertSessionHasErrors('order_id');

    expect($otherPayment->fresh()->status)->toBe('pending')
        ->and($otherOrder->fresh()->is_paid)->toBeFalse();
});

it('Floor refuses a payment method that does not match the order', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $payment] = floorOrderWithPayment($table, 'cash');

    $this->actingAs($user)
        ->post("/app/tables/{$table->id}/payments", [
            'order_id' => $order->id,
            'method' => 'pos',
        ])
        ->assertSessionHasErrors('method');

    expect($payment->fresh()->status)->toBe('pending')
        ->and($order->fresh()->is_paid)->toBeFalse();
});

it('settles a served order without an existing payment', function (): void {
    [$user, $table] = floorTableWithUser();

    $order = Order::factory()->create([
        'restaurant_id' => $table->restaurant_id,
        'table_id' => $table->id,
        'status' => OrderStatus::SERVED,
        'is_paid' => false,
        'paid_at' => null,
        'total' => 9220,
        'currency' => 'TRY',
    ]);

    expect($order->payments()->count())->toBe(0);

    $this->actingAs($user)
        ->post(route('app.tables.payments', $table), [
            'order_id' => $order->id,
            'method' => 'cash',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $order->refresh();

    expect($order->is_paid)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::PAID)
        ->and($order->payments()->count())->toBe(1);

    $payment = $order->payments()->first();

    expect($payment)->not->toBeNull()
        ->and($payment->method)->toBe('cash')
        ->and($payment->status)->toBe('paid')
        ->and($payment->amount->amount())->toBe(9220)
        ->and($payment->taken_by)->toBe($user->id)
        ->and($payment->paid_at)->not->toBeNull();

    $this->assertDatabaseHas('order_events', [
        'order_id' => $order->id,
        'to_status' => OrderStatus::PAID->value,
        'actor_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->post(route('app.tables.payments', $table), [
            'order_id' => $order->id,
            'method' => 'cash',
        ])
        ->assertSessionHasErrors('payment');

    expect($order->payments()->count())->toBe(1);
});

it('settles a POS payment without an existing payment record', function (): void {
    [$user, $table] = floorTableWithUser();

    $order = Order::factory()->create([
        'restaurant_id' => $table->restaurant_id,
        'table_id' => $table->id,
        'status' => OrderStatus::SERVED,
        'is_paid' => false,
        'paid_at' => null,
        'total' => 9220,
        'currency' => 'TRY',
    ]);

    expect($order->payments()->count())->toBe(0);

    $this->actingAs($user)
        ->post(route('app.tables.payments', $table), [
            'order_id' => $order->id,
            'method' => 'pos',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $order->refresh();

    expect($order->is_paid)->toBeTrue()
        ->and($order->status)->toBe(OrderStatus::PAID)
        ->and($order->payments()->count())->toBe(1);

    $payment = $order->payments()->first();

    expect($payment->method)->toBe('pos')
        ->and($payment->status)->toBe('paid')
        ->and($payment->amount->amount())->toBe(9220)
        ->and($payment->taken_by)->toBe($user->id)
        ->and($payment->paid_at)->not->toBeNull();
});

// ============================================================
// SEAT TABLE TESTS — ISSUE #25
// ============================================================

it('Floor seats guests at a free table', function (): void {
    [$user, $table] = floorTableWithUser();

    $table->free();

    $this->actingAs($user)
        ->post(route('app.tables.seat', $table), [
            'party_size' => 2,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $table->refresh();

    expect($table->state)->toBe(TableState::SEATED)
        ->and($table->party_size)->toBe(2)
        ->and($table->seated_at)->not->toBeNull();
});

it('Floor refuses seating more guests than table capacity', function (): void {
    [$user, $table] = floorTableWithUser();

    $table->free();

    $this->actingAs($user)
        ->post(route('app.tables.seat', $table), [
            'party_size' => $table->seats + 1,
        ])
        ->assertSessionHasErrors('party_size');

    expect($table->fresh()->state)->toBe(TableState::FREE)
        ->and($table->fresh()->party_size)->toBe(0);
});

it('Floor refuses seating zero guests', function (): void {
    [$user, $table] = floorTableWithUser();

    $table->free();

    $this->actingAs($user)
        ->post(route('app.tables.seat', $table), [
            'party_size' => 0,
        ])
        ->assertSessionHasErrors('party_size');

    expect($table->fresh()->state)->toBe(TableState::FREE);
});

it('Floor refuses seating an occupied table', function (): void {
    [$user, $table] = floorTableWithUser();

    $this->actingAs($user)
        ->post(route('app.tables.seat', $table), [
            'party_size' => 3,
        ])
        ->assertSessionHasErrors('table');

    expect($table->fresh()->state)->toBe(TableState::SEATED)
        ->and($table->fresh()->party_size)->toBe(2);
});

it('Floor hides a table from another restaurant when seating it', function (): void {
    [$user] = floorTableWithUser();

    $otherRestaurant = Restaurant::factory()->create();

    $otherTable = RestaurantTable::factory()
        ->forRestaurant($otherRestaurant)
        ->create([
            'state' => TableState::FREE,
            'party_size' => 0,
            'seated_at' => null,
        ]);

    $this->actingAs($user)
        ->post(route('app.tables.seat', $otherTable), [
            'party_size' => 2,
        ])
        ->assertNotFound();

    expect($otherTable->fresh()->state)->toBe(TableState::FREE)
        ->and($otherTable->fresh()->party_size)->toBe(0);
});

it('Floor refuses seating a free table with an unpaid order', function (): void {
    [$user, $table] = floorTableWithUser();

    $table->free();

    Order::factory()->create([
        'restaurant_id' => $table->restaurant_id,
        'table_id' => $table->id,
        'status' => OrderStatus::SERVED,
        'is_paid' => false,
        'paid_at' => null,
    ]);

    $this->actingAs($user)
        ->post(route('app.tables.seat', $table), [
            'party_size' => 2,
        ])
        ->assertSessionHasErrors('table');

    expect($table->fresh()->state)->toBe(TableState::FREE)
        ->and($table->fresh()->party_size)->toBe(0);
});

// ============================================================
// SPLIT PAYMENTS — ISSUE #25
// ============================================================

it('Floor accepts a partial cash payment and shows the remaining balance', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $payment] = floorOrderWithPayment($table, 'cash');

    $this->actingAs($user)
        ->post(route('app.tables.payments', $table), [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount_minor' => 4000,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe('paid')
        ->and($payment->fresh()->amount->amount())->toBe(4000)
        ->and($payment->fresh()->taken_by)->toBe($user->id)
        ->and($order->fresh()->is_paid)->toBeFalse()
        ->and($order->fresh()->paid_at)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::SERVED)
        ->and(
            $order->events()
                ->where('to_status', OrderStatus::PAID->value)
                ->count(),
        )->toBe(0);

    $this->actingAs($user)
        ->getJson(route('app.tables.show', $table))
        ->assertOk()
        ->assertJsonPath('open_bill_minor', 5220)
        ->assertJsonPath('orders.0.balance_minor', 5220)
        ->assertJsonPath('orders.0.paid_minor', 4000);

    $this->actingAs($user)
        ->post(route('app.tables.free', $table))
        ->assertSessionHasErrors('table');

    expect($table->fresh()->state)->toBe(TableState::SEATED);
});

it('Floor completes a split bill with a second POS payment and records one paid event', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $firstPayment] = floorOrderWithPayment($table, 'cash');

    $url = route('app.tables.payments', $table);

    $this->actingAs($user)
        ->post($url, [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount_minor' => 4000,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post($url, [
            'order_id' => $order->id,
            'method' => 'pos',
            'amount_minor' => 5220,
        ])
        ->assertSessionHasNoErrors();

    $payments = $order->payments()->orderBy('id')->get();

    expect($payments)->toHaveCount(2)
        ->and($firstPayment->fresh()->amount->amount())->toBe(4000)
        ->and($payments[1]->method)->toBe('pos')
        ->and($payments[1]->status)->toBe('paid')
        ->and($payments[1]->amount->amount())->toBe(5220)
        ->and($payments[1]->taken_by)->toBe($user->id)
        ->and($order->fresh()->is_paid)->toBeTrue()
        ->and($order->fresh()->status)->toBe(OrderStatus::PAID)
        ->and(
            $order->events()
                ->where('to_status', OrderStatus::PAID->value)
                ->count(),
        )->toBe(1);

    $this->actingAs($user)
        ->post($url, [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount_minor' => 100,
        ])
        ->assertSessionHasErrors('payment');

    expect($order->payments()->count())->toBe(2);

    $this->actingAs($user)
        ->post(route('app.tables.free', $table))
        ->assertSessionHasNoErrors();

    expect($table->fresh()->state)->toBe(TableState::FREE);
});

it('Floor refuses a payment greater than the original bill', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $payment] = floorOrderWithPayment($table);

    $this->actingAs($user)
        ->post(route('app.tables.payments', $table), [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount_minor' => 9221,
        ])
        ->assertSessionHasErrors('amount_minor');

    expect($payment->fresh()->status)->toBe('pending')
        ->and($payment->fresh()->amount->amount())->toBe(9220)
        ->and($order->fresh()->is_paid)->toBeFalse()
        ->and($order->payments()->count())->toBe(1);
});

it('Floor refuses overpayment after a partial settlement', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order] = floorOrderWithPayment($table);

    $url = route('app.tables.payments', $table);

    $this->actingAs($user)
        ->post($url, [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount_minor' => 4000,
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($user)
        ->post($url, [
            'order_id' => $order->id,
            'method' => 'pos',
            'amount_minor' => 5221,
        ])
        ->assertSessionHasErrors('amount_minor');

    expect($order->payments()->count())->toBe(1)
        ->and($order->fresh()->is_paid)->toBeFalse()
        ->and($order->fresh()->status)->toBe(OrderStatus::SERVED);
});

it('Floor refuses zero or negative split payment amounts', function (): void {
    [$user, $table] = floorTableWithUser();
    [$order, $payment] = floorOrderWithPayment($table);

    foreach ([0, -100] as $amountMinor) {
        $this->actingAs($user)
            ->post(route('app.tables.payments', $table), [
                'order_id' => $order->id,
                'method' => 'cash',
                'amount_minor' => $amountMinor,
            ])
            ->assertSessionHasErrors('amount_minor');
    }

    expect($payment->fresh()->status)->toBe('pending')
        ->and($order->fresh()->is_paid)->toBeFalse();
});
