<?php

namespace Database\Factories;

use App\Models\RestaurantHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantHour>
 */
class RestaurantHourFactory extends Factory
{
    protected $model = RestaurantHour::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => null,
            'day_of_week' => fake()->numberBetween(0, 6),
            'opens_at' => '10:00',
            'closes_at' => '23:00',
        ];
    }
}
