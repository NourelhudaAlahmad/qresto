<?php

namespace Database\Seeders;

use App\Models\Allergen;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\MenuItemVariant;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Restaurant
        $restaurant = Restaurant::factory()->create();

        // User
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'restaurant_id' => $restaurant->id,
        ]);

        // Allergens
        $allergens = Allergen::factory()
            ->count(5)
            ->create([
                'restaurant_id' => $restaurant->id,
            ]);

        // Menu Categories
        $categories = MenuCategory::factory()
            ->count(5)
            ->forRestaurant($restaurant)
            ->create();

        // Menu Items
        foreach ($categories as $category) {
            $items = MenuItem::factory()
                ->count(4)
                ->forRestaurant($restaurant)
                ->create([
                    'menu_category_id' => $category->id,
                ]);

            foreach ($items as $item) {

                // Variants
                MenuItemVariant::factory()
                    ->count(2)
                    ->create([
                        'menu_item_id' => $item->id,
                    ]);

                // Addons
                MenuItemAddon::factory()
                    ->count(2)
                    ->create([
                        'menu_item_id' => $item->id,
                    ]);

                // Allergens
                $item->allergens()->attach(
                    $allergens
                        ->random(rand(1, min(3, $allergens->count())))
                        ->mapWithKeys(fn ($allergen) => [
                            $allergen->id => [
                                'may_contain' => fake()->boolean(),
                            ],
                        ])
                        ->toArray()
                );
            }
        }
    }
}


