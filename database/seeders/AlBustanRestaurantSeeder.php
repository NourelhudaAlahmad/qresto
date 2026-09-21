<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class AlBustanRestaurantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Restaurant::updateOrCreate(
            ['slug' => 'al-bustan'],
            ['name' => 'Al Bustan',
                'slug' => 'al-bustan',

                'tagline' => 'Levantine dining in Downtown',
                'cuisine' => 'Levantine',

                'description' => 'A modern Levantine restaurant serving charcoal-grilled dishes, mezze, sweets, and refreshing drinks.',

                'address' => '14 Marsh Lane, Downtown',
                'phone' => '+1 (212) 555 0148',

                'lat' => null,
                'lng' => null,

                'currency' => 'USD',
                'service_charge_pct' => 12.50,

                'supported_locales' => [
                    'en',
                    'ar',
                ],

                'timezone' => 'America/New_York',
            ]);
    }
}
