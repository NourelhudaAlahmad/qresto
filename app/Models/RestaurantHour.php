<?php

namespace App\Models;

use App\Concerns\BelongsToRestaurant;
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
}
