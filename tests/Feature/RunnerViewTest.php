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

    $this->runner = User::query()->where('email', 'runner@al-bustan.test')->firstOrFail();
});

it('lands the runner on the pass', function () {
    $this->post(route('login.store'), ['email' => $this->runner->email, 'password' => 'password'])
        ->assertRedirect(route('app.pass', absolute: false));
});

it('shows the runner only Ready orders, through the same pass route', function () {
    $this->actingAs($this->runner)
        ->get('/app/pass')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('staff/kitchen/runner')
            ->has('tickets', 1)
            ->where('tickets.0.code', '#A-1042')
            ->where('tickets.0.status', 'ready'));
});

it('marks a plate collected: ready to served, with the runner as the actor', function () {
    $order = Order::query()->where('code', '#A-1042')->firstOrFail();

    $this->actingAs($this->runner)
        ->post("/app/kds/tickets/{$order->id}/bump")
        ->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::SERVED);

    $event = $order->events()->reorder()->latest('id')->firstOrFail();

    expect($event->from_status)->toBe('ready')
        ->and($event->to_status)->toBe('served')
        ->and($event->actor_id)->toBe($this->runner->id);

    $this->actingAs($this->runner)
        ->get('/app/pass')
        ->assertInertia(fn ($page) => $page->has('tickets', 0));
});

it('will not let the runner move a ticket the kitchen has not finished', function () {
    $cooking = Order::query()->where('code', '#A-1043')->firstOrFail();

    $this->actingAs($this->runner)
        ->post("/app/kds/tickets/{$cooking->id}/bump")
        ->assertSessionHasErrors('order');

    expect($cooking->fresh()->status)->toBe(OrderStatus::PREPARING);
});

it('keeps the runner away from payments', function () {
    $order = Order::query()->where('code', '#A-1041')->firstOrFail();

    $this->actingAs($this->runner)
        ->post("/app/tables/{$order->table_id}/payments", [
            'order_id' => $order->id,
            'method' => 'cash',
            'amount_minor' => $order->total->amount(),
        ])
        ->assertForbidden();

    $this->actingAs($this->runner)->get('/app/orders')->assertForbidden();

    expect($order->fresh()->is_paid)->toBeFalse();
});

it('still shows the kitchen the pass, not the runner view', function () {
    $kitchen = User::query()->where('email', 'kitchen@al-bustan.test')->firstOrFail();

    $this->actingAs($kitchen)
        ->get('/app/pass')
        ->assertInertia(fn ($page) => $page->component('staff/kitchen/pass'));
});

it('titles the runner\'s screen for the runner', function () {
    $this->actingAs($this->runner)
        ->get('/app/pass')
        ->assertInertia(fn ($page) => $page->where('staffLayout.pageSubtitle', 'Runner · ready to collect'));
});
