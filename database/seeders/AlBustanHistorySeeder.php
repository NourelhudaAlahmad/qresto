<?php

namespace Database\Seeders;

use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Three weeks of settled service before today, so Reports (#32) has a shape
 * to draw: busier weekends with Saturday the peak, a mix of card, wallet,
 * cash and POS, tips, and two refunds. Deterministic: the same numbers on
 * every seed. Today's live orders come from AlBustanOrdersSeeder.
 */
class AlBustanHistorySeeder extends Seeder
{
    private const DAYS = 21;

    /** Covers by weekday, Sunday = 0. Saturday is the peak. */
    private const COVERS = [0 => 38, 1 => 22, 2 => 24, 3 => 26, 4 => 30, 5 => 44, 6 => 52];

    private const METHODS = ['card', 'card', 'wallet', 'cash', 'pos'];

    public function run(): void
    {
        $restaurant = Restaurant::query()->where('slug', 'al-bustan')->firstOrFail();
        $timezone = $restaurant->timezone ?: (string) config('app.timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        $waiters = User::query()
            ->where('restaurant_id', $restaurant->id)
            ->whereIn('name', ['Nadia Rahman', 'Omar Faruk', 'Lin Wu'])
            ->orderBy('name')
            ->pluck('id')
            ->all();

        $tables = RestaurantTable::query()->where('restaurant_id', $restaurant->id)->pluck('id')->all();

        if ($waiters === [] || $tables === []) {
            return;
        }

        // Re-seeding replaces the history rather than doubling it.
        $old = DB::table('orders')
            ->where('restaurant_id', $restaurant->id)
            ->where('code', 'like', '#H-%')
            ->pluck('id');
        DB::table('payments')->whereIn('order_id', $old)->delete();
        DB::table('orders')->whereIn('id', $old)->delete();

        mt_srand(2026);

        $servicePct = (float) $restaurant->service_charge_pct;
        $refunds = 0;

        // One sequence across all days, so codes stay unique even when
        // qresto:demo-refresh moves every service day forward.
        $code = 0;

        for ($back = self::DAYS; $back >= 1; $back--) {
            $day = $today->subDays($back);
            $covers = self::COVERS[$day->dayOfWeek];
            $sequence = 1; // position within the day, for the refund rule

            while ($covers > 0) {
                $party = min($covers, mt_rand(2, 4));
                $covers -= $party;

                $placed = $day->setTime(mt_rand(17, 21), mt_rand(0, 59));
                $subtotal = $party * mt_rand(1500, 2600);
                $service = (int) round($subtotal * $servicePct / 100);
                $tip = mt_rand(0, 3) === 0 ? 0 : (int) round($subtotal * mt_rand(5, 12) / 100);
                $total = $subtotal + $service + $tip;

                $sessionId = DB::table('table_sessions')->insertGetId([
                    'restaurant_id' => $restaurant->id,
                    'restaurant_table_id' => $tables[array_rand($tables)],
                    'token' => Str::random(40),
                    'party_size' => $party,
                    'opened_at' => $placed->utc(),
                    'closed_at' => $placed->addMinutes(80)->utc(),
                    'last_seen_at' => $placed->addMinutes(80)->utc(),
                    'created_at' => $placed->utc(),
                    'updated_at' => $placed->utc(),
                ]);

                $orderId = DB::table('orders')->insertGetId([
                    'restaurant_id' => $restaurant->id,
                    'table_id' => DB::table('table_sessions')->where('id', $sessionId)->value('restaurant_table_id'),
                    'table_session_id' => $sessionId,
                    'service_date' => $day->toDateString(),
                    'code' => sprintf('#H-%05d', ++$code),
                    'assigned_user_id' => $waiters[array_rand($waiters)],
                    'status' => 'paid',
                    'placed_at' => $placed->utc(),
                    'subtotal' => $subtotal,
                    'service_pct' => $servicePct,
                    'service_amount' => $service,
                    'tip_amount' => $tip,
                    'discount_amount' => 0,
                    'total' => $total,
                    'split_ways' => 1,
                    'share_amount' => $total,
                    'is_paid' => true,
                    'paid_at' => $placed->addMinutes(75)->utc(),
                    'currency' => $restaurant->currency,
                    'created_at' => $placed->utc(),
                    'updated_at' => $placed->addMinutes(75)->utc(),
                ]);

                // Two partial refunds in the window, on weekdays.
                $refund = ($refunds < 2 && $day->dayOfWeek === 3 && $sequence++ === 3) ? (int) round($subtotal * 0.25) : 0;
                $refunds += $refund > 0 ? 1 : 0;

                DB::table('payments')->insert([
                    'order_id' => $orderId,
                    'method' => self::METHODS[array_rand(self::METHODS)],
                    'status' => 'paid',
                    'amount' => $total,
                    'tip_amount' => $tip,
                    'gateway' => 'fake',
                    'paid_at' => $placed->addMinutes(75)->utc(),
                    'refunded_amount' => $refund,
                    'refunded_at' => $refund > 0 ? $placed->addDay()->utc() : null,
                    'created_at' => $placed->addMinutes(75)->utc(),
                    'updated_at' => $placed->addMinutes(75)->utc(),
                ]);
            }
        }
    }
}
