<?php

use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantTable;

test('table can be occupied', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    $table = RestaurantTable::create([
        'restaurant_id' => $restaurant->id,
        'number' => '02',
        'seats' => 4,
        'state' => TableState::FREE,
        'party_size' => 0,
        'qr_token' => 'test-table-02',
        'sort_order' => 2,
    ]);

    $table->occupy(3);

    $table->refresh();

    expect($table->state)->toBe(TableState::SEATED)
        ->and($table->party_size)->toBe(3)
        ->and($table->seated_at)->not->toBeNull();
});
test('table can request bill', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    $table = RestaurantTable::create([
        'restaurant_id' => $restaurant->id,
        'number' => '03',
        'seats' => 4,
        'state' => TableState::ORDERED,
        'party_size' => 3,
        'qr_token' => 'test-table-03',
        'sort_order' => 3,
    ]);

    $table->requestBill();

    $table->refresh();

    expect($table->state)->toBe(TableState::BILL);
});
test('table can be freed', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    $table = RestaurantTable::create([
        'restaurant_id' => $restaurant->id,
        'number' => '04',
        'seats' => 4,
        'state' => TableState::BILL,
        'party_size' => 3,
        'seated_at' => now(),
        'qr_token' => 'test-table-04',
        'sort_order' => 4,
    ]);

    $table->free();

    $table->refresh();

    expect($table->state)->toBe(TableState::FREE)
        ->and($table->party_size)->toBe(0)
        ->and($table->seated_at)->toBeNull();
});
test('table can be marked as ordered', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    $table = RestaurantTable::create([
        'restaurant_id' => $restaurant->id,
        'number' => '05',
        'seats' => 4,
        'state' => TableState::SEATED,
        'party_size' => 3,
        'qr_token' => 'test-table-05',
        'sort_order' => 5,
    ]);

    $table->markOrdered();

    $table->refresh();

    expect($table->state)->toBe(TableState::ORDERED);
});
