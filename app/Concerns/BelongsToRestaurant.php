<?php

namespace App\Concerns;

use App\CurrentRestaurant;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToRestaurant
{
    public static function bootBelongsToRestaurant(): void
    {
        static::creating(function (Model $model) {
            if ($model->restaurant_id === null) {
                $model->restaurant_id = app(CurrentRestaurant::class)->id();
            }
        });

        static::addGlobalScope('restaurant', function (Builder $builder) {
            $restaurantId = app(CurrentRestaurant::class)->id();

            if ($restaurantId !== null) {
                $builder->where(
                    $builder->getModel()->getTable().'.restaurant_id',
                    $restaurantId,
                );
            }
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }
}
test('queries are scoped to the current restaurant', function () {
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
