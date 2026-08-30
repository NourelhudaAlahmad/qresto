<?php

use App\CurrentRestaurant;
use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantHour;
use App\Models\RestaurantTable;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);
test('restaurant is open during its opening hours', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    RestaurantHour::create([
        'restaurant_id' => $restaurant->id,
        'day_of_week' => 0,
        'opens_at' => '10:00',
        'closes_at' => '23:00',
    ]);

    $dateTime = Carbon::parse(
        '2026-08-30 12:00',
        'Asia/Riyadh',
    );

    expect($restaurant->isOpenAt($dateTime))->toBeTrue();
});

test('restaurant is closed outside its opening hours', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    RestaurantHour::create([
        'restaurant_id' => $restaurant->id,
        'day_of_week' => 0,
        'opens_at' => '10:00',
        'closes_at' => '23:00',
    ]);

    $dateTime = Carbon::parse(
        '2026-08-30 23:30',
        'Asia/Riyadh',
    );

    expect($restaurant->isOpenAt($dateTime))->toBeFalse();
});
test('restaurant is closed when the day has no opening hours', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    RestaurantHour::create([
        'restaurant_id' => $restaurant->id,
        'day_of_week' => 1,
        'opens_at' => null,
        'closes_at' => null,
    ]);

    $dateTime = Carbon::parse(
        '2026-08-31 12:00',
        'Asia/Riyadh',
    );

    expect($restaurant->isOpenAt($dateTime))->toBeFalse();
});
test('restaurant opening hours use the restaurants timezone', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    RestaurantHour::create([
        'restaurant_id' => $restaurant->id,
        'day_of_week' => 0,
        'opens_at' => '10:00',
        'closes_at' => '23:00',
    ]);

    $dateTime = Carbon::parse(
        '2026-08-30 09:00',
        'UTC',
    );

    expect($restaurant->isOpenAt($dateTime))->toBeTrue();
});

test('restaurant remains open after midnight for overnight hours', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    RestaurantHour::create([
        'restaurant_id' => $restaurant->id,
        'day_of_week' => 0,
        'opens_at' => '18:00',
        'closes_at' => '02:00',
    ]);

    $afterMidnight = Carbon::parse(
        '2026-08-31 01:00',
        'Asia/Riyadh',
    );

    expect($restaurant->isOpenAt($afterMidnight))->toBeTrue();
});

test('open_now returns the current restaurant opening status', function () {
    $restaurant = Restaurant::create([
        'name' => 'Al Bustan',
        'slug' => 'al-bustan',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    RestaurantHour::create([
        'restaurant_id' => $restaurant->id,
        'day_of_week' => 0,
        'opens_at' => '10:00',
        'closes_at' => '23:00',
    ]);

    Carbon::setTestNow(
        Carbon::parse('2026-08-30 12:00', 'Asia/Riyadh'),
    );

    expect($restaurant->open_now)->toBeTrue();

    Carbon::setTestNow(
        Carbon::parse('2026-08-30 23:30', 'Asia/Riyadh'),
    );

    expect($restaurant->open_now)->toBeFalse();

    Carbon::setTestNow();
});
test('restaurant scoped models do not leak rows from another restaurant', function () {
    $restaurantA = Restaurant::create([
        'name' => 'Restaurant A',
        'slug' => 'restaurant-a',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    $restaurantB = Restaurant::create([
        'name' => 'Restaurant B',
        'slug' => 'restaurant-b',
        'currency' => 'SAR',
        'supported_locales' => ['ar', 'en'],
        'timezone' => 'Asia/Riyadh',
    ]);

    app(CurrentRestaurant::class)->set($restaurantA);

    $tableA = RestaurantTable::create([
        'number' => '01',
        'seats' => 4,
        'state' => TableState::FREE,
        'party_size' => 0,
        'qr_token' => 'restaurant-a-table-01',
        'sort_order' => 1,
    ]);

    app(CurrentRestaurant::class)->set($restaurantB);

    $tableB = RestaurantTable::create([
        'number' => '01',
        'seats' => 4,
        'state' => TableState::FREE,
        'party_size' => 0,
        'qr_token' => 'restaurant-b-table-01',
        'sort_order' => 1,
    ]);

    app(CurrentRestaurant::class)->set($restaurantA);

    expect(RestaurantTable::count())->toBe(1)
        ->and(RestaurantTable::first()->id)->toBe($tableA->id)
        ->and(RestaurantTable::first()->id)->not->toBe($tableB->id);
});
