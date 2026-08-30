<?php

namespace Database\Seeders;

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
        $restaurant = Restaurant::factory()->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'restaurant_id' => $restaurant->id,
        ]);
    }
}



