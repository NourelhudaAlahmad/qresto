<?php

namespace App\Services\Reports;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Restaurant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Sales report (#32), built from settled payments only.
 *
 * Rows are one service day × one waiter (the order's assigned waiter).
 * - net   = payment amount − tip − refunded   (payments include the tip)
 * - tips  = payment tip
 * - covers = guests per table session, each session counted once
 * Totals: gross = amount − tip, service = service charge on fully paid
 * orders, refunds, tips; net = gross − refunds.
 *
 * Cancelled (voided) orders and failed or pending payments never count, and
 * a refund is booked on the service day of the order it refunds.
 */
class SalesReport
{
    public const PRESETS = ['today', 'week', '3weeks', 'month', 'custom'];

    /** How a payment method reads in the table, CSV and PDF. */
    public static function methodLabel(?string $method): string
    {
        return match ($method) {
            'pos' => 'POS',
            'card' => 'Card',
            'wallet' => 'Wallet',
            'cash' => 'Cash',
            null, '' => '',
            default => ucfirst($method),
        };
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable, preset: string}
     */
    public function range(Restaurant $restaurant, string $preset, ?string $start = null, ?string $end = null): array
    {
        $today = CarbonImmutable::now($restaurant->timezone ?: (string) config('app.timezone'))->startOfDay();

        return match ($preset) {
            'today' => ['start' => $today, 'end' => $today, 'preset' => 'today'],
            'week' => ['start' => $today->startOfWeek(CarbonImmutable::MONDAY), 'end' => $today, 'preset' => 'week'],
            '3weeks' => ['start' => $today->subDays(20), 'end' => $today, 'preset' => '3weeks'],
            'custom' => [
                'start' => CarbonImmutable::parse((string) $start)->startOfDay(),
                'end' => CarbonImmutable::parse((string) $end)->startOfDay(),
                'preset' => 'custom',
            ],
            default => ['start' => $today->startOfMonth(), 'end' => $today, 'preset' => 'month'],
        };
    }

    /**
     * @return array{
     *     rows: list<array{date: string, waiter: string, covers: int, orders: int, net_minor: int, tips_minor: int, method: string|null}>,
     *     totals: array{gross_minor: int, service_minor: int, refunds_minor: int, tips_minor: int, net_minor: int, covers: int, orders: int},
     *     series: list<array{date: string, net_minor: int, peak: bool}>
     * }
     */
    public function build(Restaurant $restaurant, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $groups = $this->settled($restaurant, $start, $end)
            ->leftJoin('users as u', 'u.id', '=', 'o.assigned_user_id')
            ->groupByRaw('DATE(o.service_date), o.assigned_user_id, u.name')
            ->selectRaw('DATE(o.service_date) AS day, o.assigned_user_id AS waiter_id, u.name AS waiter')
            ->selectRaw('SUM(p.amount - p.tip_amount) AS sales')
            ->selectRaw('SUM(p.tip_amount) AS tips')
            ->selectRaw('SUM(p.refunded_amount) AS refunds')
            ->selectRaw('COUNT(DISTINCT o.id) AS orders')
            ->orderBy('day')
            ->orderBy('u.name')
            ->get();

        $methods = $this->settled($restaurant, $start, $end)
            ->groupByRaw('DATE(o.service_date), o.assigned_user_id, p.method')
            ->selectRaw('DATE(o.service_date) AS day, o.assigned_user_id AS waiter_id, p.method AS method')
            ->selectRaw('SUM(p.amount) AS taken')
            ->get();

        $covers = DB::query()
            ->fromSub(
                $this->settled($restaurant, $start, $end)
                    ->join('table_sessions as ts', 'ts.id', '=', 'o.table_session_id')
                    ->selectRaw('DISTINCT DATE(o.service_date) AS day, o.assigned_user_id AS waiter_id, ts.id AS session_id, ts.party_size AS party'),
                'sessions',
            )
            ->groupBy('day', 'waiter_id')
            ->selectRaw('day, waiter_id, SUM(party) AS covers')
            ->get();

        $key = fn ($row): string => $row->day.'|'.($row->waiter_id ?? '0');

        $dominant = [];
        foreach ($methods as $row) {
            $k = $key($row);
            if (! isset($dominant[$k]) || (int) $row->taken > $dominant[$k]['taken']) {
                $dominant[$k] = ['method' => (string) $row->method, 'taken' => (int) $row->taken];
            }
        }

        $coversBy = [];
        foreach ($covers as $row) {
            $coversBy[$key($row)] = (int) $row->covers;
        }

        $rows = [];
        foreach ($groups as $row) {
            $k = $key($row);
            $rows[] = [
                'date' => (string) $row->day,
                'waiter' => $row->waiter !== null ? (string) $row->waiter : 'Unassigned',
                'covers' => $coversBy[$k] ?? 0,
                'orders' => (int) $row->orders,
                'net_minor' => (int) $row->sales - (int) $row->refunds,
                'tips_minor' => (int) $row->tips,
                'method' => $dominant[$k]['method'] ?? null,
            ];
        }

        $sums = $this->settled($restaurant, $start, $end)
            ->selectRaw('COALESCE(SUM(p.amount - p.tip_amount), 0) AS gross')
            ->selectRaw('COALESCE(SUM(p.tip_amount), 0) AS tips')
            ->selectRaw('COALESCE(SUM(p.refunded_amount), 0) AS refunds')
            ->first();

        $service = (int) DB::table('orders as o')
            ->where('o.restaurant_id', $restaurant->id)
            ->whereDate('o.service_date', '>=', $start->toDateString())
            ->whereDate('o.service_date', '<=', $end->toDateString())
            ->where('o.status', '!=', OrderStatus::CANCELLED->value)
            ->where('o.is_paid', true)
            ->sum('o.service_amount');

        $gross = (int) ($sums->gross ?? 0);
        $refunds = (int) ($sums->refunds ?? 0);

        return [
            'rows' => $rows,
            'totals' => [
                'gross_minor' => $gross,
                'service_minor' => $service,
                'refunds_minor' => $refunds,
                'tips_minor' => (int) ($sums->tips ?? 0),
                'net_minor' => $gross - $refunds,
                'covers' => array_sum(array_column($rows, 'covers')),
                'orders' => array_sum(array_column($rows, 'orders')),
            ],
            'series' => $this->series($rows, $start, $end),
        ];
    }

    /**
     * Payments that settled, on orders in the range that were not voided.
     */
    private function settled(Restaurant $restaurant, CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return DB::table('payments as p')
            ->join('orders as o', 'o.id', '=', 'p.order_id')
            ->where('o.restaurant_id', $restaurant->id)
            ->whereDate('o.service_date', '>=', $start->toDateString())
            ->whereDate('o.service_date', '<=', $end->toDateString())
            ->where('o.status', '!=', OrderStatus::CANCELLED->value)
            ->whereIn('p.status', [PaymentStatus::PAID->value, PaymentStatus::REFUNDED->value]);
    }

    /**
     * One bar per day in the range, zero-filled, with the best day flagged.
     *
     * @param  list<array{date: string, net_minor: int}>  $rows
     * @return list<array{date: string, net_minor: int, peak: bool}>
     */
    private function series(array $rows, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $byDay = [];
        foreach ($rows as $row) {
            $byDay[$row['date']] = ($byDay[$row['date']] ?? 0) + $row['net_minor'];
        }

        $series = [];
        for ($day = $start; $day->lte($end); $day = $day->addDay()) {
            $date = $day->toDateString();
            $series[] = ['date' => $date, 'net_minor' => $byDay[$date] ?? 0, 'peak' => false];
        }

        $max = max([0, ...array_column($series, 'net_minor')]);

        if ($max > 0) {
            foreach ($series as $i => $point) {
                if ($point['net_minor'] === $max) {
                    $series[$i]['peak'] = true;
                    break;
                }
            }
        }

        return $series;
    }
}
