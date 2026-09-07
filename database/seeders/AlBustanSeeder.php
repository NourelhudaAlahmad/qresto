<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class AlBustanSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AlBustanRestaurantSeeder::class,
            AlBustanHoursSeeder::class,
            AlBustanTablesSeeder::class,
            AlBustanMenuSeeder::class,
            AlBustanStaffSeeder::class,
            AlBustanOrdersSeeder::class,
        ]);
    }
}
