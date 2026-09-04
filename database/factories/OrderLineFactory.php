<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderLine>
 */
class OrderLineFactory extends Factory
{
    protected $model = OrderLine::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 4);
        $unitPrice = fake()->randomFloat(2, 5, 50);

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'name_snapshot' => fake()->words(2, true),
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'line_total' => round($unitPrice * $qty, 2),
            'note' => fake()->optional()->sentence(),
            'station' => fake()->optional()->randomElement([
                'kitchen',
                'bar',
                'grill',
            ]),
        ];
    }

    public function forOrder(Order $order): static
    {
        return $this->state(fn (array $attributes) => [
            'order_id' => $order->id,
        ]);
    }

    public function forMenuItem(MenuItem $menuItem): static
    {
        $unitPrice = $menuItem->price->amount() / 100;

        return $this->state(function (array $attributes) use ($menuItem, $unitPrice) {
            $qty = $attributes['qty'] ?? 1;

            return [
                'order_id' => $attributes['order_id'] ?? Order::factory(),
                'menu_item_id' => $menuItem->id,
                'name_snapshot' => $menuItem->name,
                'unit_price' => $unitPrice,
                'qty' => $qty,
                'line_total' => round($unitPrice * $qty, 2),
            ];
        });
    }

    public function quantity(int $qty): static
    {
        return $this->state(function (array $attributes) use ($qty) {
            $unitPrice = (float) $attributes['unit_price'];

            return [
                'qty' => $qty,
                'line_total' => round($unitPrice * $qty, 2),
            ];
        });
    }
}
