<?php

use App\Enums\OrderStatus;
use App\Models\Integration;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Database\Seeders\AlBustanSeeder;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesSeeder::class, AlBustanSeeder::class]);

    $this->restaurant = Restaurant::query()->where('slug', 'al-bustan')->firstOrFail();
    $this->kitchen = User::query()->where('email', 'kitchen@al-bustan.test')->firstOrFail();
});

function pass(User $user, array $query = []): array
{
    return test()->actingAs($user)
        ->get('/app/pass?'.http_build_query($query))
        ->assertOk()
        ->viewData('page')['props'];
}

function order(string $code): Order
{
    return Order::query()->where('code', $code)->firstOrFail();
}

it('lands the kitchen account on the pass', function () {
    $this->post(route('login.store'), ['email' => $this->kitchen->email, 'password' => 'password'])
        ->assertRedirect(route('app.pass', absolute: false));
});

it('shows the four open tickets, oldest first, and nothing ready or served', function () {
    $props = pass($this->kitchen);

    expect(array_column($props['tickets'], 'code'))
        ->toBe(['#A-1043', '#A-1045', '#A-1044', '#A-1046'])
        ->and(collect($props['tickets'])->pluck('status')->unique()->sort()->values()->all())
        ->toBe(['pending', 'preparing']);

    $kofta = collect($props['tickets'])->firstWhere('code', '#A-1043');

    expect($kofta['table'])->toBe('12')
        ->and($kofta['lines'][0]['station'])->toBe('grill')
        ->and(collect($kofta['lines'])->pluck('note')->filter()->values()->all())->toContain('no tahini');
});

it('bumps pending to preparing with the kitchen account as the actor', function () {
    $order = order('#A-1044');

    $this->actingAs($this->kitchen)
        ->post("/app/kds/tickets/{$order->id}/bump")
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::PREPARING);

    // The relation orders oldest first; reorder to read the newest event.
    $event = $order->events()->reorder()->latest('id')->firstOrFail();

    expect($event->from_status)->toBe('pending')
        ->and($event->to_status)->toBe('preparing')
        ->and($event->actor_id)->toBe($this->kitchen->id)
        ->and($event->actor_kind)->toBe('staff');
});

it('takes a ticket off the pass once it is ready', function () {
    $order = order('#A-1043'); // preparing

    $this->actingAs($this->kitchen)->post("/app/kds/tickets/{$order->id}/bump");

    expect($order->fresh()->status)->toBe(OrderStatus::READY)
        ->and(array_column(pass($this->kitchen)['tickets'], 'code'))->not->toContain('#A-1043');
});

it('refuses to bump something that is not on the pass', function () {
    $ready = order('#A-1042');

    $this->actingAs($this->kitchen)
        ->post("/app/kds/tickets/{$ready->id}/bump")
        ->assertSessionHasErrors('order');

    expect($ready->fresh()->status)->toBe(OrderStatus::READY);
});

it('filters the grid to one station and keeps only that station\'s lines', function () {
    $props = pass($this->kitchen, ['station' => 'grill']);

    $lines = collect($props['tickets'])->flatMap(fn ($ticket) => $ticket['lines']);

    expect($props['station'])->toBe('grill')
        ->and($lines)->not->toBeEmpty()
        ->and($lines->pluck('station')->unique()->values()->all())->toBe(['grill'])
        // #A-1046 is a salad and a dessert: no grill work.
        ->and(array_column($props['tickets'], 'code'))->not->toContain('#A-1046');

    $sweet = pass($this->kitchen, ['station' => 'sweet']);

    expect(array_column($sweet['tickets'], 'code'))->toBe(['#A-1046']);
});

it('rejects an unknown station', function () {
    $this->actingAs($this->kitchen)->get('/app/pass?station=bar')->assertSessionHasErrors('station');
});

it('lets a waiter bump with the capability, and stops a cashier without it', function () {
    $nadia = User::query()->where('name', 'Nadia Rahman')->firstOrFail();
    $order = order('#A-1044');

    $this->actingAs($nadia)->post("/app/kds/tickets/{$order->id}/bump")->assertRedirect();
    expect($order->fresh()->status)->toBe(OrderStatus::PREPARING);

    $cashier = User::factory()->create(['restaurant_id' => $this->restaurant->id]);
    $cashier->assignRole('cashier');

    $this->actingAs($cashier)->get('/app/pass')->assertForbidden();
    $this->actingAs($cashier)->post("/app/kds/tickets/{$order->id}/bump")->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::PREPARING);
});

it('hides another restaurant\'s order from the bump', function () {
    $other = Order::factory()->for(Restaurant::factory()->create())->create(['status' => OrderStatus::PENDING]);

    $this->actingAs($this->kitchen)->post("/app/kds/tickets/{$other->id}/bump")->assertNotFound();
});

it('reports the pass screen to system health', function () {
    $this->actingAs($this->kitchen)->post('/app/kds/heartbeat')->assertNoContent();

    $kds = Integration::query()->where('restaurant_id', $this->restaurant->id)->where('kind', 'kds')->firstOrFail();

    expect($kds->health())->toBe('operational')
        ->and($kds->detail)->toBe('pass screen up');
});

it('snapshots the kitchen station on lines of new orders', function () {
    expect(order('#A-1045')->lines()->pluck('station')->all())->toBe(['grill', 'grill'])
        ->and(order('#A-1046')->lines()->pluck('station')->all())->toBe(['cold', 'sweet']);
});

it('sits in the staff frame as "Kitchen display · Pass screen · N stations"', function () {
    $this->actingAs($this->kitchen)
        ->get('/app/pass')
        ->assertInertia(fn ($page) => $page
            ->component('staff/kitchen/pass')
            ->where('staffLayout.pageTitle', 'Kitchen display')
            ->where('staffLayout.pageSubtitle', 'Pass screen · 3 stations'));
});
