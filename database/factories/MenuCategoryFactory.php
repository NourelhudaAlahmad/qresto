<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
{
    protected $model = MenuCategory::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),

            'name' => fake()->unique()->words(2, true),

            'translations' => [
                'ar' => fake()->words(2, true),
                'en' => fake()->words(2, true),
            ],

            'sort_order' => fake()->numberBetween(1, 100),

            'is_active' => true,
        ];
    }

    public function forRestaurant(Restaurant $restaurant): static
    {
        return $this->state(fn () => [
            'restaurant_id' => $restaurant->id,
        ]);
    }
}
