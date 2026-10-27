<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManagerDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $manager = $request->user();

        abort_unless(
            $manager
                && $manager->hasRole('manager')
                && $manager->can('see_all_orders'),
            403,
        );

        /** @var Restaurant|null $restaurant */
        $restaurant = $manager->restaurant;

        abort_unless($restaurant !== null, 403);

        $restaurantId = (int) $restaurant->id;
        $timezone = $restaurant->timezone ?: config('app.timezone');
        $now = now($timezone);
        $serviceDate = $now->toDateString();
        $lastWeekDate = $now->copy()->subWeek()->toDateString();

        // Convert the restaurant's local service-day boundaries to UTC
        // for filtering timestamp columns stored in UTC.
        $serviceDayStartUtc = $now->copy()->startOfDay()->utc();
        $serviceDayEndUtc = $now->copy()->endOfDay()->utc();

        $warnMinutes = (int) config('qresto.sla.warn_minutes', 14);
        $lateMinutes = (int) config('qresto.sla.late_minutes', 25);

        /**
         * Service-day totals.
         * Covers count each table session once, even if it has multiple orders.
         */
        $covers = $this->coversForDate($restaurantId, $serviceDate);

        $lastWeekCovers = $this->coversForDate(
            $restaurantId,
            $lastWeekDate,
        );

        $netSalesMinor = $this->netSalesForDate(
            $restaurantId,
            $serviceDate,
        );

        $lastWeekSalesMinor = $this->netSalesForDate(
            $restaurantId,
            $lastWeekDate,
        );

        $avgPerCoverMinor = $covers > 0
            ? (int) round($netSalesMinor / $covers)
            : 0;

        $lastWeekAvgPerCoverMinor = $lastWeekCovers > 0
            ? (int) round($lastWeekSalesMinor / $lastWeekCovers)
            : 0;

        /**
         * Kitchen median is based on the first PREPARING and READY events
         * for each order on this service day.
         */
        $eventTimes = DB::table('order_events')
            ->select('order_id')
            ->selectRaw(
                'MIN(CASE WHEN to_status = ? THEN occurred_at END) AS preparing_at',
                [OrderStatus::PREPARING->value],
            )
            ->selectRaw(
                'MIN(CASE WHEN to_status = ? THEN occurred_at END) AS ready_at',
                [OrderStatus::READY->value],
            )
            ->groupBy('order_id');

        $prepRows = DB::table('orders as o')
            ->joinSub($eventTimes, 'event_times', function ($join): void {
                $join->on('event_times.order_id', '=', 'o.id');
            })
            ->where('o.restaurant_id', $restaurantId)
            ->where('o.service_date', $serviceDate)
            ->where('o.status', '!=', OrderStatus::CANCELLED->value)
            ->whereNotNull('event_times.preparing_at')
            ->whereNotNull('event_times.ready_at')
            ->get([
                'event_times.preparing_at',
                'event_times.ready_at',
            ]);

        $durationsSeconds = $prepRows
            ->map(
                fn (object $row): int => max(
                    0,
                    (int) Carbon::parse($row->preparing_at)
                        ->diffInSeconds(Carbon::parse($row->ready_at)),
                ),
            )
            ->sort()
            ->values()
            ->all();

        $durationCount = count($durationsSeconds);

        if ($durationCount === 0) {
            $medianPrepMinutes = null;
        } else {
            $middle = intdiv($durationCount, 2);

            $medianSeconds = $durationCount % 2 === 1
                ? $durationsSeconds[$middle]
                : (
                    $durationsSeconds[$middle - 1]
                    + $durationsSeconds[$middle]
                ) / 2;

            $medianPrepMinutes = round($medianSeconds / 60, 1);
        }

        $lateOrdersCount = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('service_date', $serviceDate)
            ->whereNotIn('status', [
                OrderStatus::PAID->value,
                OrderStatus::CANCELLED->value,
            ])
            ->where(
                'placed_at',
                '<=',
                $now->copy()->subMinutes($lateMinutes)->utc(),
            )
            ->count();

        /**
         * Unpaid totals only include unpaid orders on occupied tables
         * for the current service day.
         *
         * Money columns are already stored in minor units.
         */
        $unpaidTotal = DB::table('orders as o')
            ->join('tables as t', 't.id', '=', 'o.table_id')
            ->where('o.restaurant_id', $restaurantId)
            ->where('o.service_date', $serviceDate)
            ->where('o.is_paid', false)
            ->whereNotIn('o.status', [
                OrderStatus::PAID->value,
                OrderStatus::CANCELLED->value,
            ])
            ->whereIn('t.state', ['seated', 'ordered', 'bill'])
            ->selectRaw('COALESCE(SUM(o.total), 0) AS total')
            ->value('total');

        $unpaidTotalMinor = (int) $unpaidTotal;

        /**
         * Open bills are pending bill requests for occupied tables
         * requested during the current service day.
         *
         * Count distinct tables so duplicate requests do not inflate the card.
         */
        $openBillsQuery = DB::table('service_requests as sr')
            ->join('tables as t', 't.id', '=', 'sr.table_id')
            ->where('t.restaurant_id', $restaurantId)
            ->whereBetween('sr.requested_at', [
                $serviceDayStartUtc,
                $serviceDayEndUtc,
            ])
            ->where('sr.kind', 'bill')
            ->where('sr.status', 'pending')
            ->whereIn('t.state', ['seated', 'ordered', 'bill']);

        $openBillsCount = (clone $openBillsQuery)
            ->distinct()
            ->count('sr.table_id');

        $oldestBillAt = (clone $openBillsQuery)
            ->min('sr.requested_at');

        $oldestBillMinutes = $oldestBillAt !== null
            ? max(
                0,
                (int) Carbon::parse($oldestBillAt)->diffInMinutes($now),
            )
            : null;

        /**
         * Active orders for this restaurant on the current service day.
         */
        $orders = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('service_date', $serviceDate)
            ->whereNotIn('status', [
                OrderStatus::PAID->value,
                OrderStatus::CANCELLED->value,
            ])
            ->with([
                'table:id,number',
                'assignedUser:id,name',
            ])
            ->orderBy('placed_at')
            ->orderBy('id')
            ->get([
                'id',
                'restaurant_id',
                'table_id',
                'code',
                'guest_name',
                'assigned_user_id',
                'status',
                'service_date',
                'placed_at',
                'total',
                'is_paid',
            ])
            ->map(fn (Order $order): array => [
                'id' => $order->id,
                'code' => (string) $order->code,
                'status' => $order->status->value,
                'table' => $order->table?->number,
                'guest_name' => $order->guest_name,
                'placed_at' => $order->placed_at?->toIso8601String(),
                'age_minutes' => $order->placed_at !== null
                    ? max(
                        0,
                        (int) $order->placed_at->diffInMinutes($now),
                    )
                    : 0,
                'total_minor' => $order->total->amount(),
                'currency' => $restaurant->currency ?: 'TRY',
                'paid' => (bool) $order->is_paid,
                'assigned_waiter' => $order->assignedUser === null
                    ? null
                    : [
                        'id' => $order->assignedUser->id,
                        'name' => $order->assignedUser->name,
                    ],
            ])
            ->values()
            ->all();

        /**
         * Count active orders per waiter in SQL instead of counting them
         * inside a PHP loop. Only count orders from the current service day.
         */
        $orderCounts = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('service_date', $serviceDate)
            ->whereNotIn('status', [
                OrderStatus::PAID->value,
                OrderStatus::CANCELLED->value,
            ])
            ->select('assigned_user_id')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('assigned_user_id')
            ->pluck('aggregate', 'assigned_user_id');

        $waiters = User::query()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->role('waiter')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'job_title',
                'initials',
            ]);

        /**
         * Include shifts overlapping today in the restaurant timezone.
         * Eager-load their tables to avoid a query per waiter.
         */
        $shifts = Shift::query()
            ->where('restaurant_id', $restaurantId)
            ->where('starts_at', '<=', $now->copy()->endOfDay())
            ->where('ends_at', '>=', $now->copy()->startOfDay())
            ->with([
                'tables:id,number',
            ])
            ->orderBy('starts_at')
            ->get();

        $shiftByUser = $shifts
            ->groupBy('user_id')
            ->map(function ($userShifts) use ($now): ?Shift {
                return $userShifts->first(
                    fn (Shift $shift): bool => $shift->starts_at->lte($now)
                        && $shift->ends_at->gte($now),
                ) ?? $userShifts->first(
                    fn (Shift $shift): bool => $shift->starts_at->gte($now),
                ) ?? $userShifts->last();
            });

        $staff = $waiters
            ->map(function (User $waiter) use (
                $shiftByUser,
                $orderCounts,
                $now,
            ): array {
                $shift = $shiftByUser->get($waiter->id);

                $onShift = $shift !== null
                    && $shift->starts_at->lte($now)
                    && $shift->ends_at->gte($now);

                return [
                    'id' => $waiter->id,
                    'name' => $waiter->name,
                    'role' => 'Waiter',
                    'job_title' => $waiter->job_title,
                    'initials' => $waiter->initials,
                    'tables' => $shift === null
                        ? []
                        : $shift->tables
                            ->pluck('number')
                            ->map(fn ($number): string => (string) $number)
                            ->values()
                            ->all(),
                    'shift' => $shift === null
                        ? null
                        : [
                            'starts_at' => $shift->starts_at->format('H:i'),
                            'ends_at' => $shift->ends_at->format('H:i'),
                        ],
                    'order_count' => (int) $orderCounts->get(
                        $waiter->id,
                        0,
                    ),
                    'on_shift' => $onShift,
                ];
            })
            ->values()
            ->all();

        return Inertia::render('staff/manager/overview', [
            'stats' => [
                'covers' => $covers,
                'covers_last_week' => $lastWeekCovers,
                'net_sales_minor' => $netSalesMinor,
                'net_sales_last_week_minor' => $lastWeekSalesMinor,
                'avg_per_cover_minor' => $avgPerCoverMinor,
                'avg_per_cover_last_week_minor' => $lastWeekAvgPerCoverMinor,
                'median_prep_minutes' => $medianPrepMinutes,
                'late_orders_count' => $lateOrdersCount,
                'unpaid_total_minor' => $unpaidTotalMinor,
                'open_bills' => $openBillsCount,
                'oldest_bill_minutes' => $oldestBillMinutes,
            ],
            'orders' => $orders,
            'staff' => $staff,
            'waiters' => $waiters->map(fn (User $waiter): array => [
                'id' => $waiter->id,
                'name' => $waiter->name,
            ])->values()->all(),
            // Table numbers offered in the "Add a waiter" drawer.
            'tables' => DB::table('tables')
                ->where('restaurant_id', $restaurantId)
                ->orderBy('sort_order')
                ->orderBy('number')
                ->pluck('number')
                ->map(fn ($number): string => (string) $number)
                ->values()
                ->all(),
            'now' => $now->valueOf(),
            'currency' => $restaurant->currency ?: 'TRY',
            'warnMinutes' => $warnMinutes,
            'lateMinutes' => $lateMinutes,
            'pollIntervalMs' => max(
                1000,
                (int) config('qresto.live.floor', 8) * 1000,
            ),
        ]);
    }

    private function coversForDate(
        int $restaurantId,
        string $serviceDate,
    ): int {
        $sessionCovers = DB::table('orders as o')
            ->join('table_sessions as ts', 'ts.id', '=', 'o.table_session_id')
            ->where('o.restaurant_id', $restaurantId)
            ->where('o.service_date', $serviceDate)
            ->where('o.status', '!=', OrderStatus::CANCELLED->value)
            ->select('o.table_session_id')
            ->selectRaw('MAX(ts.party_size) AS covers')
            ->groupBy('o.table_session_id');

        return (int) DB::query()
            ->fromSub($sessionCovers, 'session_covers')
            ->sum('covers');
    }

    private function netSalesForDate(
        int $restaurantId,
        string $serviceDate,
    ): int {
        $netSales = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('service_date', $serviceDate)
            ->where('is_paid', true)
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->selectRaw(
                'COALESCE(SUM(subtotal - discount_amount), 0) AS net_sales',
            )
            ->value('net_sales');

        // Values are already in minor units; do not multiply by 100.
        return (int) $netSales;
    }

    public function assign(Request $request, Order $order): RedirectResponse
    {
        $manager = $request->user();

        abort_unless(
            $manager
                && $manager->hasRole('manager')
                && $manager->can('see_all_orders'),
            403,
        );

        abort_unless(
            (int) $order->restaurant_id === (int) $manager->restaurant_id,
            404,
        );

        $validated = $request->validate([
            'assigned_user_id' => ['required', 'integer'],
        ]);

        $waiter = User::query()
            ->whereKey($validated['assigned_user_id'])
            ->where('restaurant_id', $manager->restaurant_id)
            ->where('is_active', true)
            ->role('waiter')
            ->first();

        if ($waiter === null) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'Select an active waiter from this restaurant.',
            ]);
        }

        DB::transaction(function () use ($order, $manager, $waiter): void {
            $locked = Order::query()
                ->where('restaurant_id', $manager->restaurant_id)
                ->lockForUpdate()
                ->findOrFail($order->id);

            if (in_array($locked->status, [
                OrderStatus::PAID,
                OrderStatus::CANCELLED,
            ], true)) {
                throw ValidationException::withMessages([
                    'order' => 'Only active orders can be reassigned.',
                ]);
            }

            $previousWaiterId = $locked->assigned_user_id;

            if ((int) $previousWaiterId === (int) $waiter->id) {
                return;
            }

            $locked->assigned_user_id = max(1, (int) $waiter->id);
            $locked->save();

            $locked->events()->create([
                'from_status' => $locked->status->value,
                'to_status' => $locked->status->value,
                'actor_id' => $manager->id,
                'actor_kind' => 'staff',
                'occurred_at' => now(),
                'meta' => [
                    'action' => 'waiter_reassigned',
                    'from_waiter_id' => $previousWaiterId,
                    'to_waiter_id' => $waiter->id,
                ],
            ]);
        });

        return back()->with('success', 'Order reassigned successfully.');
    }
}
