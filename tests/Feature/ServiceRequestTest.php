<?php

use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use Database\Seeders\RolesSeeder;

function createServiceRequestContext20(): array
{
    $restaurant = Restaurant::factory()->create();

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
        'number' => '12',
        'state' => TableState::SEATED,
    ]);

    $session = TableSession::factory()->create([
        'restaurant_id' => $restaurant->id,
        'restaurant_table_id' => $table->id,
        'guest_name' => 'Nour Alahmad',
        'opened_at' => now(),
        'last_seen_at' => now()->subMinutes(5),
        'closed_at' => null,
    ]);

    return [
        'restaurant' => $restaurant,
        'table' => $table,
        'session' => $session,
    ];
}

it('creates a bill request and moves the table to bill state', function (): void {
    $context = createServiceRequestContext20();

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(
            route('guest.service-requests.store'),
            [
                'kind' => 'bill',
            ],
        )
        ->assertStatus(302);

    $request = ServiceRequest::query()->first();

    expect($request)
        ->not->toBeNull()
        ->and($request->kind->value)
        ->toBe('bill')
        ->and($request->status)
        ->toBe('pending')
        ->and($request->table_id)
        ->toBe($context['table']->id)
        ->and($request->table_session_id)
        ->toBe($context['session']->id);

    expect(
        $context['table']->refresh()->state,
    )->toBe(TableState::BILL);
});

it('rejects a rapid second identical open request', function (): void {
    $context = createServiceRequestContext20();

    $cookie = [
        'qresto_table_session',
        $context['session']->token,
    ];

    $this
        ->withCookie(...$cookie)
        ->post(
            route('guest.service-requests.store'),
            [
                'kind' => 'water',
            ],
        )
        ->assertStatus(302);

    $this
        ->withCookie(...$cookie)
        ->post(
            route('guest.service-requests.store'),
            [
                'kind' => 'water',
            ],
        )
        ->assertStatus(429);

    expect(
        ServiceRequest::query()
            ->where(
                'table_session_id',
                $context['session']->id,
            )
            ->where('kind', 'water')
            ->where('status', 'pending')
            ->count(),
    )->toBe(1);
});

it('acknowledges a request with the staff actor and removes it from the open list', function (): void {
    $context = createServiceRequestContext20();

    $this->seed(RolesSeeder::class);

    $staff = User::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'is_active' => true,
    ]);

    $staff->assignRole('waiter');

    $serviceRequest = ServiceRequest::factory()
        ->forSession($context['session'])
        ->water()
        ->pending()
        ->create();

    $this
        ->actingAs($staff)
        ->patch(
            route(
                'service-requests.acknowledge',
                $serviceRequest,
            ),
        )
        ->assertStatus(302);

    $serviceRequest->refresh();

    expect($serviceRequest->status)
        ->toBe('acknowledged')
        ->and($serviceRequest->acknowledged_by)
        ->toBe($staff->id)
        ->and($serviceRequest->acknowledged_at)
        ->not->toBeNull();

    expect(
        ServiceRequest::query()
            ->whereKey($serviceRequest->id)
            ->where('status', 'pending')
            ->exists(),
    )->toBeFalse();
});

it('cannot create a request for a table outside the current session', function (): void {
    $context = createServiceRequestContext20();

    $otherTable = RestaurantTable::factory()->create([
        'restaurant_id' => $context['restaurant']->id,
        'number' => '13',
        'state' => TableState::SEATED,
    ]);

    $this
        ->withCookie(
            'qresto_table_session',
            $context['session']->token,
        )
        ->post(
            route('guest.service-requests.store'),
            [
                'kind' => 'waiter',
                'table_id' => $otherTable->id,
            ],
        )
        ->assertForbidden();

    expect(
        ServiceRequest::query()->count(),
    )->toBe(0);
});
