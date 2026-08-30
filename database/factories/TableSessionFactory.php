<?php

namespace Database\Factories;

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
            'restaurant_id' => null,
            'restaurant_table_id' => null,
            'token' => fake()->unique()->sha256(),
            'guest_name' => fake()->firstName(),
            'party_size' => fake()->numberBetween(1, 6),
            'opened_at' => $openedAt,
            'closed_at' => null,
            'last_seen_at' => $openedAt,
        ];
    }
}