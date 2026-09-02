<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    protected $model = MenuItem::class;

    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'menu_category_id' => MenuCategory::factory(),

            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),

            'translations' => [
                'ar' => fake()->words(3, true),
                'tr' => fake()->words(3, true),
            ],

            // 1250 = 12.50 TRY
            'price' => fake()->numberBetween(500, 5000),

            'photo_path' => null,

            'prep_minutes' => fake()->numberBetween(5, 30),

            'is_available' => true,
            'is_scheduled' => false,

            'available_from' => null,
            'available_until' => null,

            'sort_order' => fake()->numberBetween(0, 100),

            'dietary_tags' => fake()->randomElements(
                ['vegan', 'vegetarian', 'nut-free'],
                fake()->numberBetween(0, 2),
            ),

            'chef_flag' => false,

            'updated_by' => null,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn () => [
            'is_available' => false,
        ]);
    }

    public function chefsPick(): static
    {
        return $this->state(fn () => [
            'chef_flag' => true,
        ]);
    }

    public function scheduled(
        ?\DateTimeInterface $from = null,
        ?\DateTimeInterface $until = null,
    ): static {
        return $this->state(fn () => [
            'is_scheduled' => true,
            'available_from' => $from ?? now()->subHour(),
            'available_until' => $until ?? now()->addHour(),
        ]);
    }

    public function forRestaurant(Restaurant $restaurant): static
    {
        return $this->state(fn () => [
            'restaurant_id' => $restaurant->id,
        ]);
    }

    public function withVariants(int $count = 2): static
    {
        return $this->afterCreating(function (MenuItem $menuItem) use ($count) {
            MenuItemVariant::factory()
                ->count($count)
                ->create([
                    'menu_item_id' => $menuItem->id,
                ]);
        });
    }

    public function withAddons(int $count = 2): static
    {
        return $this->afterCreating(function (MenuItem $menuItem) use ($count) {
            MenuItemAddon::factory()
                ->count($count)
                ->create([
                    'menu_item_id' => $menuItem->id,
                ]);
        });
    }
}
