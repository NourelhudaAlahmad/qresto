<?php

namespace Database\Factories;

use App\Models\Allergen;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Allergen>
 */
class AllergenFactory extends Factory
{
    protected $model = Allergen::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),

            'name' => fake()->randomElement([
                'Nuts',
                'Peanuts',
                'Milk',
                'Eggs',
                'Gluten',
                'Fish',
                'Shellfish',
                'Soy',
            ]),

            'translations' => [
                'ar' => fake()->word(),
                'tr' => fake()->word(),
                'en' => fake()->word(),
            ],
        ];
    }
}
