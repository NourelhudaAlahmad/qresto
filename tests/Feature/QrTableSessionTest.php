<?php

use App\CurrentRestaurant;
use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function createQrRestaurant(string $slug = 'al-bustan'): Restaurant
{
    return Restaurant::factory()->create([
        'name' => 'Al Bustan',
        'slug' => $slug,
        'timezone' => 'Europe/Istanbul',
    ]);
}

function createQrTable(
    Restaurant $restaurant,
    string $number,
    string $qrToken,
    int $sortOrder = 1,
): RestaurantTable {
    app(CurrentRestaurant::class)->set($restaurant);

    return RestaurantTable::create([
        'restaurant_id' => $restaurant->id,
        'number' => $number,
        'seats' => 4,
        'state' => TableState::FREE,
        'party_size' => 0,
        'qr_token' => $qrToken,
        'sort_order' => $sortOrder,
    ]);
}

it('opens a table session from a valid qr token and sets the session cookie', function (): void {
    $restaurant = createQrRestaurant();

    $table = createQrTable(
        $restaurant,
        '12',
        'table-12-valid-token',
    );

    $response = $this->get(
        route('table.show', [
            'qrToken' => $table->qr_token,
        ]),
    );

    $response
        ->assertOk()
        ->assertCookie(
            config(
                'qresto.table_session_cookie',
                'qresto_table_session',
            ),
        );

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('guest/table')
            ->where('restaurant.name', 'Al Bustan')
            ->where('table.id', $table->id)
            ->where('table.number', '12')
            ->where('table.seats', 4)
            ->where('qr_token', 'table-12-valid-token'),
    );

    expect(
        TableSession::withoutGlobalScopes()->count(),
    )->toBe(1);

    $session = TableSession::withoutGlobalScopes()->first();

    expect($session)
        ->not->toBeNull()
        ->and($session->restaurant_id)->toBe($restaurant->id)
        ->and($session->restaurant_table_id)->toBe($table->id)
        ->and($session->isActive())->toBeTrue();
});

it('reuses the same live session when the same table is scanned twice', function (): void {
    $restaurant = createQrRestaurant();

    $table = createQrTable(
        $restaurant,
        '12',
        'table-12-reuse-token',
    );

    $this->get(
        route('table.show', [
            'qrToken' => $table->qr_token,
        ]),
    )->assertOk();

    $firstSession = TableSession::withoutGlobalScopes()
        ->firstOrFail();

    $this->get(
        route('table.show', [
            'qrToken' => $table->qr_token,
        ]),
    )->assertOk();

    $secondSession = TableSession::withoutGlobalScopes()
        ->firstOrFail();

    expect($secondSession->id)
        ->toBe($firstSession->id)
        ->and(
            TableSession::withoutGlobalScopes()->count(),
        )
        ->toBe(1);
});

it('returns not found for an unknown qr token', function (): void {
    createQrRestaurant();

    $this->get(
        route('table.show', [
            'qrToken' => 'unknown-qr-token',
        ]),
    )->assertNotFound();
});

it('stores the guest first name and redirects to the menu', function (): void {
    $restaurant = createQrRestaurant();

    $table = createQrTable(
        $restaurant,
        '12',
        'table-12-confirm-token',
    );

    $this->get(
        route('table.show', [
            'qrToken' => $table->qr_token,
        ]),
    )->assertOk();

    $response = $this->post(
        route('table.confirm', [
            'qrToken' => $table->qr_token,
        ]),
        [
            'first_name' => 'Rami',
        ],
    );

    $response
        ->assertRedirect(route('menu'))
        ->assertCookie(
            config(
                'qresto.table_session_cookie',
                'qresto_table_session',
            ),
        );

    $session = TableSession::withoutGlobalScopes()
        ->firstOrFail();

    expect($session->guest_name)->toBe('Rami');
});

it('redirects menu requests without a table session to the landing page', function (): void {
    $this->get(route('menu'))
        ->assertRedirect(route('home'))
        ->assertSessionHas(
            'error',
            'Your table session is missing. Please scan the QR code again.',
        );
});

it('shows only tables from the current restaurant in the table picker', function (): void {
    $restaurant = createQrRestaurant('al-bustan');
    $otherRestaurant = createQrRestaurant('other-restaurant');

    $currentTable = createQrTable(
        $restaurant,
        '12',
        'al-bustan-table-12',
        12,
    );

    $otherTable = createQrTable(
        $restaurant,
        '11',
        'al-bustan-table-11',
        11,
    );

    createQrTable(
        $otherRestaurant,
        '99',
        'other-restaurant-table-99',
        1,
    );

    app(CurrentRestaurant::class)->set($restaurant);

    $response = $this->get(
        route('table.tables', [
            'qrToken' => $currentTable->qr_token,
        ]),
    );

    $response->assertOk();

    $response->assertInertia(
        fn (Assert $page) => $page
            ->component('guest/tables')
            ->where(
                'current_table_id',
                $currentTable->id,
            )
            ->has('tables', 2)
            ->where('tables.0.id', $otherTable->id)
            ->where('tables.0.number', '11')
            ->where(
                'tables.0.url',
                route('table.show', [
                    'qrToken' => $otherTable->qr_token,
                ]),
            )
            ->where('tables.1.id', $currentTable->id)
            ->where('tables.1.number', '12'),
    );
});

it('returns 404 for a revoked qr token', function (): void {
    $restaurant = createQrRestaurant();

    $table = createQrTable(
        $restaurant,
        '12',
        'revoked-table-token',
    );

    $table->update([
        'qr_token' => 'replacement-table-token',
    ]);

    $this->get('/t/revoked-table-token')
        ->assertNotFound();

    $this->get('/t/replacement-table-token')
        ->assertOk();
});

it('returns the waiter assigned to the table during the active shift', function (): void {
    $restaurant = createQrRestaurant();

    $table = createQrTable(
        $restaurant,
        '12',
        'table-12-waiter-token',
    );

    $waiter = User::factory()->create([
        'restaurant_id' => $restaurant->id,
        'name' => 'Nadia Rahman',
        'initials' => 'NR',
    ]);

    app(CurrentRestaurant::class)->set($restaurant);

    $shift = Shift::create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $waiter->id,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addHour(),
    ]);

    $shift->tables()->attach($table->id, [
        'user_id' => $waiter->id,
    ]);

    $response = $this->get(
        route('table.show', [
            'qrToken' => $table->qr_token,
        ]),
    );

    $response
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('guest/table')
                ->where('table.number', '12')
                ->where(
                    'assigned_waiter.name',
                    'Nadia Rahman',
                )
                ->where(
                    'assigned_waiter.initials',
                    'NR',
                ),
        );
});
