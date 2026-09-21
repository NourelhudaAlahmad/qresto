<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AlBustanShiftsSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'al-bustan')->firstOrFail();

        $assignments = [
            'Nadia Rahman' => ['09', '10', '11', '12'],
            'Omar Faruk' => ['05', '06', '07', '08'],
            'Lin Wu' => ['01', '02', '03', '04'],
        ];

        foreach ($assignments as $waiterName => $tableNumbers) {
            $waiter = User::where('restaurant_id', $restaurant->id)
                ->where('name', $waiterName)
                ->firstOrFail();

            $shift = Shift::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $waiter->id,
                ],
                [
                    'starts_at' => now()->subHours(4),
                    'ends_at' => now()->addHours(8),
                ],
            );

            $tables = $restaurant->tables()
                ->whereIn('number', $tableNumbers)
                ->get();

            DB::table('table_user')
                ->where('shift_id', $shift->id)
                ->delete();

            foreach ($tables as $table) {
                DB::table('table_user')->insert([
                    'shift_id' => $shift->id,
                    'user_id' => $waiter->id,
                    'table_id' => $table->id,
                ]);
            }
        }
    }
}
