<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tonight's floor team, matching the design's STAFF list
 * (docs/design/03-staff-panels.dc.html):
 *
 *   Nadia Rahman  Waiter · tables 04, 12 · on shift
 *   Omar Faruk    Waiter · table 02      · on shift (the bar has no table row)
 *   Lin Wu        Waiter · tables 07, 09 · off at 18:00, so off shift now
 *   Sara Malik    Manager                · on shift, no tables
 *
 * Times are relative to now so the on/off states are true whenever the
 * demo is seeded. Lin's shift ended half an hour ago; her tables stay on
 * that shift as history, and #A-1041 on Table 07 is still hers.
 */
class AlBustanShiftsSeeder extends Seeder
{
    public function run(): void
    {
        $restaurant = Restaurant::where('slug', 'al-bustan')->firstOrFail();

        $now = now();

        $shifts = [
            'Nadia Rahman' => [
                'tables' => ['04', '12'],
                'starts_at' => $now->copy()->subHours(2),
                'ends_at' => $now->copy()->addHours(4),
            ],
            'Omar Faruk' => [
                'tables' => ['02'],
                'starts_at' => $now->copy()->subHours(2),
                'ends_at' => $now->copy()->addHours(4),
            ],
            'Lin Wu' => [
                'tables' => ['07', '09'],
                'starts_at' => $now->copy()->subHours(5),
                'ends_at' => $now->copy()->subMinutes(30),
            ],
            'Sara Malik' => [
                'tables' => [],
                'starts_at' => $now->copy()->subHours(3),
                'ends_at' => $now->copy()->addHours(5),
            ],
        ];

        foreach ($shifts as $name => $plan) {
            $member = User::where('restaurant_id', $restaurant->id)
                ->where('name', $name)
                ->firstOrFail();

            $shift = Shift::updateOrCreate(
                [
                    'restaurant_id' => $restaurant->id,
                    'user_id' => $member->id,
                ],
                [
                    'starts_at' => $plan['starts_at'],
                    'ends_at' => $plan['ends_at'],
                ],
            );

            DB::table('table_user')
                ->where('shift_id', $shift->id)
                ->delete();

            $tables = $restaurant->tables()
                ->whereIn('number', $plan['tables'])
                ->get();

            foreach ($tables as $table) {
                DB::table('table_user')->insert([
                    'shift_id' => $shift->id,
                    'user_id' => $member->id,
                    'table_id' => $table->id,
                ]);
            }
        }
    }
}
