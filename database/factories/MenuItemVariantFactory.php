<?php

namespace Database\Factories;

use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItemVariant>
 */
class MenuItemVariantFactory extends Factory
{
    protected $model = MenuItemVariant::class;

    public function definition(): array
    {
        return [
            'menu_item_id' => MenuItem::factory(),
            'label' => fake()->randomElement([
                'Small',
                'Medium',
                'Large',
            ]),
            'price_delta' => fake()->numberBetween(0, 1000),
            'is_default' => false,
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => [
            'is_default' => true,
        ]);
    }
}

