<?php

namespace Database\Factories;

use App\Enums\TableState;
use App\Models\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantTable>
 */
class RestaurantTableFactory extends Factory
{
    protected $model = RestaurantTable::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => null,
            'number' => fake()->unique()->numberBetween(1, 99),
            'seats' => fake()->numberBetween(2, 8),
            'state' => TableState::FREE,
            'party_size' => 0,
            'seated_at' => null,
            'qr_token' => fake()->unique()->sha256(),
            'sort_order' => fake()->numberBetween(1, 99),
        ];
    }
}
