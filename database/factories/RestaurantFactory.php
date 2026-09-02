<?php

namespace Database\Factories;

use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Restaurant>
 */
class RestaurantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'slug' => fake()->unique()->slug(),
            'tagline' => fake()->sentence(4),
            'cuisine' => fake()->randomElement([
                'Arabic',
                'Turkish',
                'Italian',
                'International',
            ]),
            'description' => fake()->paragraph(),
            'address' => fake()->address(),
            'phone' => fake()->phoneNumber(),
            'lat' => fake()->latitude(),
            'lng' => fake()->longitude(),
            'currency' => 'TRY',
            'service_charge_pct' => 10.00,
            'default_locale' => 'en',
            'supported_locales' => ['en', 'tr', 'ar'],
            'timezone' => 'Europe/Istanbul',
        ];
    }
}
