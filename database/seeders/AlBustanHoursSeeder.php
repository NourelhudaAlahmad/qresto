<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use Illuminate\Database\Seeder;

class AlBustanHoursSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'al-bustan')->firstOrFail();

        $hours = [
            ['day_of_week' => 0, 'opens_at' => '12:00', 'closes_at' => '22:00'],
            ['day_of_week' => 1, 'opens_at' => '17:00', 'closes_at' => '23:00'],
            ['day_of_week' => 2, 'opens_at' => '17:00', 'closes_at' => '23:00'],
            ['day_of_week' => 3, 'opens_at' => '17:00', 'closes_at' => '23:00'],
            ['day_of_week' => 4, 'opens_at' => '17:00', 'closes_at' => '23:00'],
            ['day_of_week' => 5, 'opens_at' => '12:00', 'closes_at' => '00:00'],
            ['day_of_week' => 6, 'opens_at' => '12:00', 'closes_at' => '00:00'],
        ];

        foreach ($hours as $hour) {
            $restaurant->hours()->updateOrCreate(
                [
                    'day_of_week' => $hour['day_of_week'],
                ],
                [
                    'opens_at' => $hour['opens_at'],
                    'closes_at' => $hour['closes_at'],
                ],
            );
        }
    }
}
