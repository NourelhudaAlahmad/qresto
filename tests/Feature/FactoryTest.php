<?php

use App\CurrentRestaurant;
use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantHour;
use App\Models\RestaurantTable;
use App\Models\Shift;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('all issue 4 factories can create their models', function () {
    $restaurant = Restaurant::factory()->create();

    app(CurrentRestaurant::class)->set($restaurant);

    $user = User::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $hour = RestaurantHour::factory()->create([
        'restaurant_id' => $restaurant->id,
    ]);

    $table = RestaurantTable::factory()->create([
        'restaurant_id' => $restaurant->id,
        'state' => TableState::FREE,
    ]);

    $session = TableSession::factory()->create([
        'restaurant_id' => $restaurant->id,
        'restaurant_table_id' => $table->id,
    ]);

    $shift = Shift::factory()->create([
        'restaurant_id' => $restaurant->id,
        'user_id' => $user->id,
    ]);

    expect($restaurant)->toBeInstanceOf(Restaurant::class)
        ->and($hour)->toBeInstanceOf(RestaurantHour::class)
        ->and($table)->toBeInstanceOf(RestaurantTable::class)
        ->and($session)->toBeInstanceOf(TableSession::class)
        ->and($shift)->toBeInstanceOf(Shift::class);
});
