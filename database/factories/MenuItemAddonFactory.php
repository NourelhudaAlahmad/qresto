<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemAddon>
 */
class MenuItemAddonFactory extends Factory
{
    protected $model = MenuItemAddon::class;

    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),

            'label' => fake()->randomElement([
                'Extra Cheese',
                'Extra Sauce',
                'Fries',
                'Pickles',
            ]),

            'price_delta' => fake()->numberBetween(100, 1000),

            'is_available' => true,

            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn () => [
            'is_available' => false,
        ]);
    }
}

