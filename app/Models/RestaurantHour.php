<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
use App\Support\LandingCache;
use Database\Factories\RestaurantHourFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RestaurantHour extends Model
{
    use BelongsToRestaurant;

    /** @use HasFactory<RestaurantHourFactory> */
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'day_of_week',
        'opens_at',
        'closes_at',
    ];

    protected static function booted(): void
    {
        static::saved(function (RestaurantHour $hour): void {
            LandingCache::invalidate($hour->restaurant_id);
        });

        static::deleted(function (RestaurantHour $hour): void {
            LandingCache::invalidate($hour->restaurant_id);
        });
    }
}
