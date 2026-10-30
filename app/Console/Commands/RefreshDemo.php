<?php

namespace App\Console\Commands;

use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Seeders\AlBustanShiftsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bring the demo restaurant's clock up to "now" without wiping anything.
 *
 * The seed writes times relative to the moment it runs: the newest order is
 * two minutes old, tonight's shifts started two hours ago. A day later every
 * ticket is "19h 27m" late and every shift has ended, so waiters see no
 * tables. This moves all of the restaurant's times forward by the same
 * amount, so the room looks as it did just after seeding, and restarts
 * tonight's shifts. Orders, payments and anything added since stay put.
 */
class RefreshDemo extends Command
{
    protected $signature = 'qresto:demo-refresh
        {--restaurant=al-bustan : Slug of the demo restaurant}';

    protected $description = 'Move the demo restaurant\'s times up to now, without wiping data';

    /** The seed's newest order is this old; keep it that way. */
    private const NEWEST_ORDER_AGE_MINUTES = 2;

    public function handle(): int
    {
        $slug = $this->option('restaurant');

        $restaurant = Restaurant::query()
            ->where('slug', is_string($slug) ? $slug : 'al-bustan')
            ->first();

        if ($restaurant === null) {
            $this->error('No restaurant with that slug. Seed the demo first: php artisan migrate:fresh --seed');

            return self::FAILURE;
        }

        $newest = DB::table('orders')
            ->where('restaurant_id', $restaurant->id)
            ->max('placed_at');

        if ($newest === null) {
            $this->warn('The demo has no orders to move. Restarting tonight\'s shifts only.');
            $this->restartShifts();

            return self::SUCCESS;
        }

        $target = CarbonImmutable::now()->subMinutes(self::NEWEST_ORDER_AGE_MINUTES);
        $seconds = (int) CarbonImmutable::parse((string) $newest)->diffInSeconds($target, false);

        if ($seconds <= 60) {
            $this->info('The demo is already current.');
            $this->restartShifts();

            return self::SUCCESS;
        }

        $timezone = $restaurant->timezone ?: (string) config('app.timezone');

        DB::transaction(function () use ($restaurant, $seconds, $timezone): void {
            $orderIds = DB::table('orders')->where('restaurant_id', $restaurant->id)->pluck('id');
            $tableIds = DB::table('tables')->where('restaurant_id', $restaurant->id)->pluck('id');

            $this->shift('orders', ['placed_at', 'paid_at', 'voided_at', 'created_at', 'updated_at'], 'id', $orderIds, $seconds);
            $this->shift('order_events', ['occurred_at', 'created_at', 'updated_at'], 'order_id', $orderIds, $seconds);
            $this->shift('payments', ['paid_at', 'refunded_at', 'created_at', 'updated_at'], 'order_id', $orderIds, $seconds);
            $this->shift('tables', ['seated_at'], 'id', $tableIds, $seconds);
            $this->shift('service_requests', ['requested_at', 'acknowledged_at', 'created_at', 'updated_at'], 'table_id', $tableIds, $seconds);
            $this->shift('table_sessions', ['opened_at', 'closed_at', 'last_seen_at', 'created_at', 'updated_at'], 'restaurant_table_id', $tableIds, $seconds);

            // Each order belongs to the service day its new time falls on.
            DB::table('orders')
                ->whereIn('id', $orderIds)
                ->whereNotNull('placed_at')
                ->get(['id', 'placed_at'])
                ->each(fn ($order) => DB::table('orders')->where('id', $order->id)->update([
                    'service_date' => CarbonImmutable::parse((string) $order->placed_at, 'UTC')
                        ->setTimezone($timezone)
                        ->toDateString(),
                ]));
        });

        $this->restartShifts();

        $this->info(sprintf(
            'Moved the demo forward by %s and restarted tonight\'s shifts.',
            CarbonImmutable::now()->subSeconds($seconds)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE),
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $columns
     * @param  Collection<int, mixed>  $ids
     */
    private function shift(string $table, array $columns, string $key, $ids, int $seconds): void
    {
        if ($ids->isEmpty()) {
            return;
        }

        $rows = DB::table($table)->whereIn($key, $ids->all())->get(array_merge(['id'], $columns));

        foreach ($rows as $row) {
            $changes = [];

            foreach ($columns as $column) {
                if ($row->{$column} !== null) {
                    $changes[$column] = CarbonImmutable::parse((string) $row->{$column}, 'UTC')
                        ->addSeconds($seconds)
                        ->toDateTimeString();
                }
            }

            if ($changes !== []) {
                DB::table($table)->where('id', $row->id)->update($changes);
            }
        }
    }

    private function restartShifts(): void
    {
        app(AlBustanShiftsSeeder::class)->run();
    }
}
