<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderLine;
use App\Support\Money;
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

        $unitPrice = Money::fromMinor(
            fake()->numberBetween(500, 5000),
        );

        $lineTotal = $unitPrice->multiply($qty);

        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'name_snapshot' => fake()->words(2, true),
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'line_total' => $lineTotal,
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
        return $this->state(function (array $attributes) use ($menuItem) {
            $qty = (int) ($attributes['qty'] ?? 1);

            $unitPrice = $menuItem->price instanceof Money
                ? $menuItem->price
                : Money::fromDecimal((string) $menuItem->price);

            return [
                'order_id' => $attributes['order_id'] ?? Order::factory(),
                'menu_item_id' => $menuItem->id,
                'name_snapshot' => $menuItem->name,
                'unit_price' => $unitPrice,
                'qty' => $qty,
                'line_total' => $unitPrice->multiply($qty),
            ];
        });
    }

    public function quantity(int $qty): static
    {
        return $this->state(function (array $attributes) use ($qty) {
            $unitPrice = $attributes['unit_price'] ?? Money::fromMinor(0);

            if (! $unitPrice instanceof Money) {
                $unitPrice = is_int($unitPrice)
                    ? Money::fromMinor($unitPrice)
                    : Money::fromDecimal((string) $unitPrice);
            }

            return [
                'qty' => $qty,
                'line_total' => $unitPrice->multiply($qty),
            ];
        });
    }
}
