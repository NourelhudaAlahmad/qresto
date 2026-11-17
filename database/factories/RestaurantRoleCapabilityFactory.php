<?php

namespace Database\Factories;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\Restaurant;
use App\Models\RestaurantRoleCapability;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RestaurantRoleCapability>
 */
class RestaurantRoleCapabilityFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'role' => fake()->randomElement(Role::cases())->value,
            'capability' => fake()->randomElement(Capability::cases())->value,
            'granted' => true,
        ];
    }

    public function revoked(): static
    {
        return $this->state(fn (array $attributes) => ['granted' => false]);
    }
}
