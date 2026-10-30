<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, AlBustanSeeder::class]);

    $this->cashier = User::query()->where('email', 'cashier@al-bustan.test')->firstOrFail();
});

it('lands the cashier on the unpaid bills', function () {
    $this->post(route('login.store'), ['email' => $this->cashier->email, 'password' => 'password'])
        ->assertRedirect(route('app.orders', absolute: false));
});

it('lists only unpaid orders, through the same orders route, with totals and tables', function () {
    $this->actingAs($this->cashier)
        ->get('/app/orders')
        ->assertOk()
        ->assertInertia(function ($page) {
            $page->component('staff/cashier');

            $orders = collect($page->toArray()['props']['orders']);

            expect($orders->pluck('code')->sort()->values()->all())->toBe(['#A-1041', '#A-1044'])
                ->and($orders->pluck('paid')->unique()->all())->toBe([false])
                ->and($orders->every(fn ($order) => $order['total_minor'] > 0 && $order['table_id'] !== null))->toBeTrue();

            return $page;
        });
});

it('settles one through the floor\'s own payment action', function () {
    $order = Order::query()->where('code', '#A-1041')->firstOrFail();

    // The bill the checkout loads.
    $this->actingAs($this->cashier)
        ->getJson("/app/tables/{$order->table_id}")
        ->assertOk();

    $this->actingAs($this->cashier)
        ->post("/app/tables/{$order->table_id}/payments", [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount_minor' => $order->total->amount(),
        ])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->is_paid)->toBeTrue();

    $this->actingAs($this->cashier)
        ->get('/app/orders')
        ->assertInertia(fn ($page) => $page
            ->has('orders', 1)
            ->where('orders.0.code', '#A-1044'));
});

it('keeps the cashier away from the kitchen and from seating tables', function () {
    $order = Order::query()->where('code', '#A-1044')->firstOrFail();

    $this->actingAs($this->cashier)->get('/app/pass')->assertForbidden();
    $this->actingAs($this->cashier)->post("/app/kds/tickets/{$order->id}/bump")->assertForbidden();
    $this->actingAs($this->cashier)->patch("/app/orders/{$order->id}/advance")->assertForbidden();
    $this->actingAs($this->cashier)->post("/app/tables/{$order->table_id}/seat", ['party_size' => 2])->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::PENDING);
});

it('still shows a manager the full order board', function () {
    $sara = User::query()->where('email', 'sara.malik@al-bustan.test')->firstOrFail();

    $this->actingAs($sara)
        ->get('/app/orders')
        ->assertInertia(fn ($page) => $page->component('staff/orders/kanban'));
});
