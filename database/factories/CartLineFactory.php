<?php

namespace Database\Factories;

use App\Models\Cart;
use App\Models\CartLine;
use App\Models\MenuItem;
use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CartLine>
 */
class CartLineFactory extends Factory
{
    protected $model = CartLine::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 4);

        $unitPrice = Money::fromMinor(
            fake()->numberBetween(500, 5000),
            (string) config('qresto.currency'),
        );

        return [
            'cart_id' => Cart::factory(),
            'menu_item_id' => MenuItem::factory(),
            'name_snapshot' => fake()->words(2, true),
            'unit_price' => $unitPrice,
            'qty' => $qty,
            'line_total' => $unitPrice->multiply($qty),
            'note' => null,
        ];
    }

    public function forCart(Cart $cart): static
    {
        return $this->state(fn (array $attributes) => [
            'cart_id' => $cart->id,
        ]);
    }

    public function forMenuItem(MenuItem $menuItem): static
    {
        return $this->state(function (array $attributes) use ($menuItem) {
            $qty = (int) ($attributes['qty'] ?? 1);

            $unitPrice = $menuItem->price instanceof Money
                ? $menuItem->price
                : Money::fromDecimal((string) $menuItem->price, (string) config('qresto.currency'));

            return [
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
            $unitPrice = $attributes['unit_price'] ?? Money::zero((string) config('qresto.currency'));

            if (! $unitPrice instanceof Money) {
                $unitPrice = is_int($unitPrice)
                    ? Money::fromMinor($unitPrice, (string) config('qresto.currency'))
                    : Money::fromDecimal((string) $unitPrice, (string) config('qresto.currency'));
            }

            return [
                'qty' => $qty,
                'line_total' => $unitPrice->multiply($qty),
            ];
        });
    }
}
