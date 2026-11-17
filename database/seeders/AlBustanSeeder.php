<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;

class AlBustanSeeder extends Seeder
{
    public function run(): void
    {
        // The demo's staff all sign in with "password", the admin included.
        // A stray SEED_DEMO_DATA=true must never put them on a live server.
        if (app()->isProduction()) {
            throw new RuntimeException('The Al Bustan demo cannot be seeded in production: its staff accounts share a known password.');
        }

        $this->call([
            AlBustanRestaurantSeeder::class,
            AlBustanHoursSeeder::class,
            AlBustanTablesSeeder::class,
            AlBustanMenuSeeder::class,
            AlBustanStaffSeeder::class,
            AlBustanShiftsSeeder::class,
            AlBustanOrdersSeeder::class,
            AlBustanHistorySeeder::class,
            AlBustanIntegrationsSeeder::class,
        ]);
    }
}
