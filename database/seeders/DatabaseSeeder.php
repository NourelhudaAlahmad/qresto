<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
        ]);

        if (config('demo.seed_demo_data')) {
            $this->call([
                AlBustanSeeder::class,
            ]);
        }
    }
}
