<?php

namespace Database\Factories;

use App\Models\Promotion;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now();
        $endsAt = now()->addDays(30);

        return [
            'restaurant_id' => Restaurant::factory(),
            'code' => strtoupper(fake()->unique()->bothify('PROMO-####')),
            'kind' => fake()->randomElement(['percent', 'amount']),
            'value' => fake()->randomFloat(2, 5, 30),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'max_uses' => fake()->optional()->numberBetween(10, 100),
            'uses' => 0,
        ];
    }

    public function forRestaurant(Restaurant $restaurant): static
    {
        return $this->state(fn (array $attributes) => [
            'restaurant_id' => $restaurant->id,
        ]);
    }

    public function percent(float $value = 10): static
    {
        return $this->state([
            'kind' => 'percent',
            'value' => $value,
        ]);
    }

    public function amount(float $value = 5): static
    {
        return $this->state([
            'kind' => 'amount',
            'value' => $value,
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(30),
        ]);
    }

    public function expired(): static
    {
        return $this->state([
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(),
        ]);
    }

    public function unlimited(): static
    {
        return $this->state([
            'max_uses' => null,
        ]);
    }

    public function used(int $uses): static
    {
        return $this->state([
            'uses' => $uses,
        ]);
    }
}
