<?php

use App\CurrentRestaurant;
use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;

test('table session can be created for current restaurant', function () {
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
        'qr_token' => 'session-test-table-01',
        'sort_order' => 1,
    ]);

    $session = TableSession::create([
        'restaurant_table_id' => $table->id,
        'token' => 'session-test-01',
        'party_size' => 1,
        'opened_at' => now(),
        'last_seen_at' => now(),
    ]);

    expect($session->restaurant_id)->toBe($restaurant->id)
        ->and($session->table->id)->toBe($table->id)
        ->and($session->isActive())->toBeTrue()
        ->and($session->closed_at)->toBeNull();
});

test('table session can be ended', function () {
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
        'qr_token' => 'session-test-table-02',
        'sort_order' => 1,
    ]);

    $session = TableSession::create([
        'restaurant_table_id' => $table->id,
        'token' => 'session-test-02',
        'party_size' => 1,
        'opened_at' => now(),
        'last_seen_at' => now(),
    ]);

    expect($session->isActive())->toBeTrue();

    $session->end();
    $session->refresh();

    expect($session->isActive())->toBeFalse()
        ->and($session->closed_at)->not->toBeNull();
});