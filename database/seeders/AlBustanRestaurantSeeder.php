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

                // Copy from the design's landing artboard.
                'tagline' => 'Charcoal, citrus, and a long table.',
                'cuisine' => 'Modern Levantine',

                'description' => 'Twenty-eight seats around an open grill. The menu changes with what the market sends, so it is short and it is written every morning. We keep the lights low and the bread coming.',

                'translations' => [
                    'en' => [
                        'intro' => 'Al Bustan cooks over live fire, seven nights a week.',
                    ],
                    'ar' => [
                        'name' => 'البستان',
                        'tagline' => 'فحم، حمضيات، ومائدة طويلة.',
                        'cuisine' => 'مطبخ شامي حديث',
                        'intro' => 'البستان يطبخ على نار حيّة، سبع ليالٍ في الأسبوع.',
                        'description' => 'ثمانية وعشرون مقعدًا حول شواية مفتوحة. تتغيّر القائمة بحسب ما يصلنا من السوق، لذلك هي قصيرة وتُكتب كل صباح. نُبقي الإضاءة خافتة والخبز حاضرًا دائمًا.',
                        'address' => '14 شارع مارش، وسط المدينة',
                    ],
                ],

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
