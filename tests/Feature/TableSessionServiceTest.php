<?php

use App\CurrentRestaurant;
use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Services\TableSessionService;

test('session can be started from a table qr token', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    app(CurrentRestaurant::class)->set($restaurant);

    $table = RestaurantTable::create([
        'number' => '01',
        'seats' => 4,
        'state' => TableState::FREE,
        'party_size' => 0,
        'qr_token' => 'service-test-table-01',
        'sort_order' => 1,
    ]);

    $session = app(TableSessionService::class)
        ->startFromQrToken('service-test-table-01');

    expect($session)
        ->toBeInstanceOf(TableSession::class)
        ->and($session->restaurant_id)->toBe($restaurant->id)
        ->and($session->restaurant_table_id)->toBe($table->id)
        ->and($session->table->id)->toBe($table->id)
        ->and($session->isActive())->toBeTrue();
});

test('session cannot be started from an unknown qr token', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    app(CurrentRestaurant::class)->set($restaurant);

    expect(fn () => app(TableSessionService::class)
        ->startFromQrToken('unknown-qr-token'))
        ->toThrow(RuntimeException::class, 'Table not found.');
});
test('opening a session on an active table reuses the existing session', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    app(CurrentRestaurant::class)->set($restaurant);

    $table = RestaurantTable::create([
        'number' => '01',
        'seats' => 4,
        'state' => TableState::FREE,
        'party_size' => 0,
        'qr_token' => 'reuse-test-table-01',
        'sort_order' => 1,
    ]);

    $service = app(TableSessionService::class);

    $firstSession = $service->startFromQrToken('reuse-test-table-01');
    $secondSession = $service->startFromQrToken('reuse-test-table-01');

    expect($secondSession->id)->toBe($firstSession->id)
        ->and(TableSession::withoutGlobalScopes()->count())->toBe(1);
});
