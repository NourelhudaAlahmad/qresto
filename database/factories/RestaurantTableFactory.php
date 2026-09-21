<?php

namespace Database\Factories;

use App\Enums\TableState;
use App\Models\Restaurant;
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
            'restaurant_id' => Restaurant::factory(),
            'number' => fake()->unique()->numberBetween(1, 99),
            'seats' => fake()->numberBetween(2, 8),
            'state' => TableState::FREE,
            'party_size' => 0,
            'seated_at' => null,
            'qr_token' => fake()->unique()->sha256(),
            'sort_order' => fake()->numberBetween(1, 99),
        ];
    }

    public function forRestaurant(Restaurant $restaurant): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $restaurant->id,
        ]);
    }
}
