<?php

namespace Database\Factories;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TableSession>
 */
class TableSessionFactory extends Factory
{
    protected $model = TableSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $openedAt = now();

        return [
            'restaurant_id' => Restaurant::factory(),
            'restaurant_table_id' => RestaurantTable::factory(),
            'token' => fake()->unique()->sha256(),
            'guest_name' => fake()->firstName(),
            'party_size' => fake()->numberBetween(1, 6),
            'opened_at' => $openedAt,
            'closed_at' => null,
            'last_seen_at' => $openedAt,
        ];
    }

    public function forTable(RestaurantTable $table): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $table->restaurant_id,
            'restaurant_table_id' => $table->id,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now(),
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => null,
        ]);
    }
}
