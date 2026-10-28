<?php

use App\Enums\OrderStatus;
use App\Enums\TableState;
use App\Mail\ReceiptMail;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Permission::findOrCreate('take_payment', 'web');

    $waiter = Role::findOrCreate('waiter', 'web');
    $waiter->givePermissionTo('take_payment');
});

function emailReceiptFixtures(): array
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
    ]);

    $url = "/app/tables/{$table->id}/orders/{$order->id}/receipt/email";

    return [$restaurant, $user, $table, $order, $url];
}

it('queues a receipt email for an authorized waiter', function (): void {
    Mail::fake();

    [, $user, , $order, $url] = emailReceiptFixtures();

    $this->actingAs($user)
        ->post($url, [
            'email' => 'guest@example.com',
        ])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    Mail::assertQueued(ReceiptMail::class, function (ReceiptMail $mail) use ($order): bool {
        return $mail->hasTo('guest@example.com')
            && $mail->order->is($order);
    });

    Mail::assertQueued(ReceiptMail::class, 1);
});

it('redirects guests to login', function (): void {
    Mail::fake();

    [, , , , $url] = emailReceiptFixtures();

    $this->post($url, [
        'email' => 'guest@example.com',
    ])->assertRedirect(route('login'));

    Mail::assertNothingOutgoing();
});

it('rejects an invalid email address', function (): void {
    Mail::fake();

    [, $user, , , $url] = emailReceiptFixtures();

    $this->actingAs($user)
        ->post($url, [
            'email' => 'not-an-email',
        ])
        ->assertSessionHasErrors(['email']);

    Mail::assertNothingOutgoing();
});

it('requires an email address', function (): void {
    Mail::fake();

    [, $user, , , $url] = emailReceiptFixtures();

    $this->actingAs($user)
        ->post($url, [])
        ->assertSessionHasErrors(['email']);

    Mail::assertNothingOutgoing();
});

it('forbids a user without payment permission', function (): void {
    Mail::fake();

    [$restaurant, , , , $url] = emailReceiptFixtures();

    $viewer = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $this->actingAs($viewer)
        ->post($url, [
            'email' => 'guest@example.com',
        ])
        ->assertForbidden();

    Mail::assertNothingOutgoing();
});

it('hides receipts from another restaurant when emailing', function (): void {
    Mail::fake();

    [, , , , $url] = emailReceiptFixtures();

    $otherRestaurant = Restaurant::factory()->create();

    $otherUser = User::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
    ]);
    $otherUser->assignRole('waiter');

    $this->actingAs($otherUser)
        ->post($url, [
            'email' => 'guest@example.com',
        ])
        ->assertNotFound();

    Mail::assertNothingOutgoing();
});

it('rejects an order belonging to a different table', function (): void {
    Mail::fake();

    [$restaurant, $user, , $order] = emailReceiptFixtures();

    $otherTable = RestaurantTable::factory()
        ->forRestaurant($restaurant)
        ->create();

    assignTonight($user, $otherTable);

    $url = "/app/tables/{$otherTable->id}/orders/{$order->id}/receipt/email";

    $this->actingAs($user)
        ->post($url, [
            'email' => 'guest@example.com',
        ])
        ->assertNotFound();

    Mail::assertNothingOutgoing();
});

it('rejects an order belonging to another restaurant', function (): void {
    Mail::fake();

    [, $user, $table] = emailReceiptFixtures();

    $otherRestaurant = Restaurant::factory()->create();

    $otherOrder = Order::factory()->create([
        'restaurant_id' => $otherRestaurant->id,
        'table_id' => null,
    ]);

    $url = "/app/tables/{$table->id}/orders/{$otherOrder->id}/receipt/email";

    $this->actingAs($user)
        ->post($url, [
            'email' => 'guest@example.com',
        ])
        ->assertNotFound();

    Mail::assertNothingOutgoing();
});
