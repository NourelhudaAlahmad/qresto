<?php

namespace App\Http\Controllers;

use App\Enums\Capability;
use App\Enums\OrderStatus;
use App\Http\Requests\Staff\StaffMemberRequest;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ManagerDashboardController extends Controller
{
    /**
     * Table states that mean a party is sitting at the table.
     *
     * @var list<string>
     */
    private const OCCUPIED_TABLE_STATES = ['seated', 'ordered', 'bill'];

    public function index(Request $request): Response
    {
        $manager = $request->user();

        // Capabilities, not role names: the matrix is editable (#7, #28).
        abort_unless(
            $manager
                && $manager->can(Capability::MANAGE_WAITER_ACCOUNTS->value)
                && $manager->can(Capability::SEE_ALL_ORDERS->value),
            403,
        );

        /** @var Restaurant|null $restaurant */
        $restaurant = $manager->restaurant;

        abort_unless($restaurant !== null, 403);

        $restaurantId = (int) $restaurant->id;
        $timezone = $restaurant->timezone ?: config('app.timezone');
        $now = now($timezone);
        $currency = $restaurant->currency ?: config('qresto.currency');
        $lateMinutes = (int) config('qresto.sla.late_minutes', 25);

        /*
         * Every prop is a closure (#54): the page polls stats, orders,
         * staff and now, and Inertia only runs the closures it sends.
         */
        return Inertia::render('staff/manager/overview', [
            'stats' => fn (): array => $this->stats($restaurantId, $now, $lateMinutes),
            'orders' => fn (): array => $this->activeOrders($restaurantId, $now, $currency),
            'staff' => fn (): array => $this->staff($restaurantId, $now, $timezone),
            'waiters' => fn (): array => User::query()
                ->where('restaurant_id', $restaurantId)
                ->where('is_active', true)
                ->whereHas('roles', fn (Builder $roles) => $roles->where('name', 'waiter'))
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (User $waiter): array => [
                    'id' => $waiter->id,
                    'name' => $waiter->name,
                ])
                ->values()
                ->all(),
            // Table numbers offered in the "Add a waiter" drawer.
            'tables' => fn (): array => DB::table('tables')
                ->where('restaurant_id', $restaurantId)
                ->orderBy('sort_order')
                ->orderBy('number')
                ->pluck('number')
                ->map(fn ($number): string => (string) $number)
                ->values()
                ->all(),
            // Roles the add/edit drawer offers (#27).
            'staffRoles' => StaffMemberRequest::manageableRoles(),
            'now' => fn (): float => $now->valueOf(),
            'currency' => $currency,
            'warnMinutes' => (int) config('qresto.sla.warn_minutes', 14),
            'lateMinutes' => $lateMinutes,
            'pollIntervalMs' => max(
                1000,
                (int) config('qresto.live.floor', 8) * 1000,
            ),
        ]);
    }

    /**
     * The service-day cards, compared with the same weekday last week.
     *
     * Four queries whatever the size of the day: covers, one
     * conditional-aggregate pass over the two days' orders, kitchen
     * prep times and the open bill calls.
     *
     * @return array{covers: int, covers_last_week: int, net_sales_minor: int, net_sales_last_week_minor: int, avg_per_cover_minor: int, avg_per_cover_last_week_minor: int, median_prep_minutes: float|null, late_orders_count: int, unpaid_total_minor: int, open_bills: int, oldest_bill_minutes: int|null}
     */
    private function stats(int $restaurantId, Carbon $now, int $lateMinutes): array
    {
        $serviceDate = $now->toDateString();
        $lastWeekDate = $now->copy()->subWeek()->toDateString();
        $closed = [OrderStatus::PAID->value, OrderStatus::CANCELLED->value];

        /*
         * Covers count each table session once per day, even if it has
         * several orders.
         */
        $sessionCovers = DB::table('orders as o')
            ->join('table_sessions as ts', 'ts.id', '=', 'o.table_session_id')
            ->where('o.restaurant_id', $restaurantId)
            ->whereIn('o.service_date', [$serviceDate, $lastWeekDate])
            ->where('o.status', '!=', OrderStatus::CANCELLED->value)
            ->select('o.service_date', 'o.table_session_id')
            ->selectRaw('MAX(ts.party_size) AS covers')
            ->groupBy('o.service_date', 'o.table_session_id');

        $coversByDate = DB::query()
            ->fromSub($sessionCovers, 'session_covers')
            ->select('service_date')
            ->selectRaw('SUM(covers) AS covers')
            ->groupBy('service_date')
            ->pluck('covers', 'service_date');

        $covers = (int) $coversByDate->get($serviceDate, 0);
        $lastWeekCovers = (int) $coversByDate->get($lastWeekDate, 0);

        /*
         * Net sales for both days, late tickets and the unpaid total on
         * occupied tables share one pass over the orders. Money columns
         * are already stored in minor units.
         */
        $totals = DB::table('orders as o')
            ->leftJoin('tables as t', 't.id', '=', 'o.table_id')
            ->where('o.restaurant_id', $restaurantId)
            ->whereIn('o.service_date', [$serviceDate, $lastWeekDate])
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN o.service_date = ? AND o.is_paid = ? AND o.status != ? THEN o.subtotal - o.discount_amount ELSE 0 END), 0) AS net_sales',
                [$serviceDate, true, OrderStatus::CANCELLED->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN o.service_date = ? AND o.is_paid = ? AND o.status != ? THEN o.subtotal - o.discount_amount ELSE 0 END), 0) AS last_week_net_sales',
                [$lastWeekDate, true, OrderStatus::CANCELLED->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN o.service_date = ? AND o.status NOT IN (?, ?) AND o.placed_at <= ? THEN 1 ELSE 0 END), 0) AS late_orders',
                [$serviceDate, ...$closed, $now->copy()->subMinutes($lateMinutes)->utc()],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN o.service_date = ? AND o.is_paid = ? AND o.status NOT IN (?, ?) AND t.state IN (?, ?, ?) THEN o.total ELSE 0 END), 0) AS unpaid_total',
                [$serviceDate, false, ...$closed, ...self::OCCUPIED_TABLE_STATES],
            )
            ->first();

        $netSalesMinor = (int) ($totals->net_sales ?? 0);
        $lastWeekSalesMinor = (int) ($totals->last_week_net_sales ?? 0);

        /*
         * Open bills are pending bill calls from occupied tables made
         * during the current service day. Count distinct tables so
         * duplicate requests do not inflate the card.
         */
        $bills = DB::table('service_requests as sr')
            ->join('tables as t', 't.id', '=', 'sr.table_id')
            ->where('t.restaurant_id', $restaurantId)
            ->whereBetween('sr.requested_at', [
                $now->copy()->startOfDay()->utc(),
                $now->copy()->endOfDay()->utc(),
            ])
            ->where('sr.kind', 'bill')
            ->where('sr.status', 'pending')
            ->whereIn('t.state', self::OCCUPIED_TABLE_STATES)
            ->selectRaw('COUNT(DISTINCT sr.table_id) AS open_bills')
            ->selectRaw('MIN(sr.requested_at) AS oldest_at')
            ->first();

        $oldestBillAt = $bills->oldest_at ?? null;

        return [
            'covers' => $covers,
            'covers_last_week' => $lastWeekCovers,
            'net_sales_minor' => $netSalesMinor,
            'net_sales_last_week_minor' => $lastWeekSalesMinor,
            'avg_per_cover_minor' => $covers > 0
                ? (int) round($netSalesMinor / $covers)
                : 0,
            'avg_per_cover_last_week_minor' => $lastWeekCovers > 0
                ? (int) round($lastWeekSalesMinor / $lastWeekCovers)
                : 0,
            'median_prep_minutes' => $this->medianPrepMinutes($restaurantId, $serviceDate),
            'late_orders_count' => (int) ($totals->late_orders ?? 0),
            'unpaid_total_minor' => (int) ($totals->unpaid_total ?? 0),
            'open_bills' => (int) ($bills->open_bills ?? 0),
            'oldest_bill_minutes' => $oldestBillAt !== null
                ? max(0, (int) Carbon::parse($oldestBillAt)->diffInMinutes($now))
                : null,
        ];
    }

    /**
     * Kitchen median, from the first PREPARING and READY events of each
     * of the day's orders. Joins from the day's orders, so the whole
     * event log is not grouped on every poll.
     */
    private function medianPrepMinutes(int $restaurantId, string $serviceDate): ?float
    {
        $durationsSeconds = DB::table('orders as o')
            ->join('order_events as e', 'e.order_id', '=', 'o.id')
            ->where('o.restaurant_id', $restaurantId)
            ->where('o.service_date', $serviceDate)
            ->where('o.status', '!=', OrderStatus::CANCELLED->value)
            ->whereIn('e.to_status', [
                OrderStatus::PREPARING->value,
                OrderStatus::READY->value,
            ])
            ->groupBy('o.id')
            ->selectRaw(
                'MIN(CASE WHEN e.to_status = ? THEN e.occurred_at END) AS preparing_at',
                [OrderStatus::PREPARING->value],
            )
            ->selectRaw(
                'MIN(CASE WHEN e.to_status = ? THEN e.occurred_at END) AS ready_at',
                [OrderStatus::READY->value],
            )
            ->get()
            ->filter(fn (object $row): bool => $row->preparing_at !== null && $row->ready_at !== null)
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
            return null;
        }

        $middle = intdiv($durationCount, 2);

        $medianSeconds = $durationCount % 2 === 1
            ? $durationsSeconds[$middle]
            : ($durationsSeconds[$middle - 1] + $durationsSeconds[$middle]) / 2;

        return round($medianSeconds / 60, 1);
    }

    /**
     * Active orders for this restaurant on the current service day.
     *
     * @return array<int, array<string, mixed>>
     */
    private function activeOrders(int $restaurantId, Carbon $now, string $currency): array
    {
        return Order::query()
            ->where('restaurant_id', $restaurantId)
            ->where('service_date', $now->toDateString())
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
            // currency is selected so the money cast does not load the
            // restaurant once per order.
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
                'currency',
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
                    ? max(0, (int) $order->placed_at->diffInMinutes($now))
                    : 0,
                'total_minor' => $order->total->amount(),
                'currency' => $currency,
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
    }

    /**
     * The floor team the manager can manage: every waiter, runner,
     * cashier and kitchen account, deactivated ones included so they
     * can be reactivated. Active people first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function staff(int $restaurantId, Carbon $now, string $timezone): array
    {
        $orderCounts = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('service_date', $now->toDateString())
            ->whereNotIn('status', [
                OrderStatus::PAID->value,
                OrderStatus::CANCELLED->value,
            ])
            ->select('assigned_user_id')
            ->selectRaw('COUNT(*) AS aggregate')
            ->groupBy('assigned_user_id')
            ->pluck('aggregate', 'assigned_user_id');

        // whereHas on the role names rather than ->role(): the scope
        // first looks every role up by name, one query each.
        $team = User::query()
            ->where('restaurant_id', $restaurantId)
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', StaffMemberRequest::manageableRoles()))
            ->with('roles:id,name')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'email',
                'job_title',
                'initials',
                'is_active',
                'deactivated_at',
            ]);

        /*
         * Shifts overlapping today in the restaurant timezone, with their
         * tables eager-loaded to avoid a query per person.
         */
        $shiftByUser = Shift::query()
            ->where('restaurant_id', $restaurantId)
            ->where('starts_at', '<=', $now->copy()->endOfDay())
            ->where('ends_at', '>=', $now->copy()->startOfDay())
            ->with(['tables:id,number'])
            ->orderBy('starts_at')
            ->get()
            ->groupBy('user_id')
            ->map(function ($userShifts) use ($now): ?Shift {
                return $userShifts->first(
                    fn (Shift $shift): bool => $shift->starts_at->lte($now)
                        && $shift->ends_at->gte($now),
                ) ?? $userShifts->first(
                    fn (Shift $shift): bool => $shift->starts_at->gte($now),
                ) ?? $userShifts->last();
            });

        return $team
            ->map(function (User $member) use ($shiftByUser, $orderCounts, $now, $timezone): array {
                $active = $member->is_active && $member->deactivated_at === null;
                $role = (string) ($member->roles->first()->name ?? 'waiter');

                $shift = $active ? $shiftByUser->get($member->id) : null;

                $onShift = $shift !== null
                    && $shift->starts_at->lte($now)
                    && $shift->ends_at->gte($now);

                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $role,
                    'role_label' => ucfirst($role),
                    'job_title' => $member->job_title,
                    'active' => $active,
                    'deactivated_at' => $member->deactivated_at?->toIso8601String(),
                    'initials' => $member->initials,
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
                            'starts_at' => $shift->starts_at->copy()->timezone($timezone)->format('H:i'),
                            'ends_at' => $shift->ends_at->copy()->timezone($timezone)->format('H:i'),
                        ],
                    'order_count' => (int) $orderCounts->get($member->id, 0),
                    'on_shift' => $onShift,
                ];
            })
            ->values()
            ->all();
    }

    public function assign(Request $request, Order $order): RedirectResponse
    {
        $manager = $request->user();

        abort_unless(
            $manager
                && $manager->can(Capability::MANAGE_WAITER_ACCOUNTS->value),
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
                'assigned_user_id' => __('messages.orders.reassign_waiter'),
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
                    'order' => __('messages.orders.reassign_active_only'),
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
