<?php

namespace Database\Factories;

use App\Models\OrderLine;
use App\Models\OrderLineOption;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLineOption>
 */
class OrderLineOptionFactory extends Factory
{
    protected $model = OrderLineOption::class;

    public function definition(): array
    {
        return [
            'order_line_id' => OrderLine::factory(),
            'kind' => fake()->randomElement([
                'variant',
                'addon',
            ]),
            'label_snapshot' => fake()->words(2, true),
            'price_delta' => fake()->randomFloat(2, -5, 10),
        ];
    }

    public function variant(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'variant',
        ]);
    }

    public function addon(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => 'addon',
        ]);
    }

    public function forOrderLine(OrderLine $orderLine): static
    {
        return $this->state(fn (array $attributes) => [
            'order_line_id' => $orderLine->id,
        ]);
    }

    public function free(): static
    {
        return $this->state(fn (array $attributes) => [
            'price_delta' => 0,
        ]);
    }

    public function priceDelta(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'price_delta' => $amount,
        ]);
    }
}
