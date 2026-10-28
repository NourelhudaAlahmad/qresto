<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettlePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\TableState;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Floor\TableAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class FloorController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless(
            $user !== null && $user->restaurant_id !== null,
            403,
        );

        $access = app(TableAccess::class);

        // A waiter's "My tables" is their live-shift assignment; managers
        // and admins see the whole floor.
        $onlyTableIds = $access->seesWholeFloor($user)
            ? null
            : $access->assignedTableIds($user);

        return Inertia::render('staff/floor', [
            'tables' => fn (): array => $this->floorTables(
                (int) $user->restaurant_id,
                $onlyTableIds,
            ),
        ]);
    }

    public function show(
        Request $request,
        RestaurantTable $table,
    ): JsonResponse {
        $this->authorizeRestaurant($request, $table);

        $orders = Order::query()
            ->where('restaurant_id', $table->restaurant_id)
            ->where('table_id', $table->id)
            ->where('is_paid', false)
            ->where('status', '!=', OrderStatus::CANCELLED->value)
            ->with(['lines', 'payments'])
            ->orderBy('id')
            ->get();

        $requests = ServiceRequest::query()
            ->where('table_id', $table->id)
            ->where('status', 'pending')
            ->orderBy('requested_at')
            ->get();

        $orderData = $orders->map(function (Order $order): array {
            $paidMinor = $this->paidMinor($order);

            $balanceMinor = max(
                0,
                $order->total->amount() - $paidMinor,
            );

            return [
                'id' => $order->id,
                'status' => $order->status->value,
                'currency' => $order->currency,
                'is_paid' => (bool) $order->is_paid,

                'lines' => $order->lines->map(
                    fn (OrderLine $line): array => [
                        'id' => $line->id,
                        'name' => $line->name_snapshot,
                        'qty' => $line->qty,
                        'unit_price_minor' => $line->unit_price->amount(),
                        'line_total_minor' => $line->line_total->amount(),
                        'note' => $line->note,
                    ],
                )->values()->all(),

                'payments' => $order->payments->map(
                    fn (Payment $payment): array => [
                        'id' => $payment->id,
                        'method' => $payment->method,
                        'status' => $payment->status,
                        'amount_minor' => $payment->amount->amount(),
                        'taken_by' => $payment->taken_by,
                        'paid_at' => $payment->paid_at?->toIso8601String(),
                    ],
                )->values()->all(),

                'paid_minor' => $paidMinor,
                'total_minor' => $order->total->amount(),
                'balance_minor' => $balanceMinor,
            ];
        })->values()->all();

        $openBillMinor = array_sum(
            array_column($orderData, 'balance_minor'),
        );

        return response()->json([
            'table' => [
                'id' => $table->id,
                'number' => $table->number,
                'state' => $table->state->value,
                'seats' => $table->seats,
                'party_size' => $table->party_size,
                'seated_at' => $table->seated_at?->toIso8601String(),
            ],

            'orders' => $orderData,

            'open_bill_minor' => $openBillMinor,

            'currency' => $orders->first()?->getAttribute('currency')
                ?? $table->restaurant?->getAttribute('currency')
                ?? 'TRY',

            'service_requests' => $requests->map(
                fn (ServiceRequest $serviceRequest): array => [
                    'id' => $serviceRequest->id,
                    'kind' => $serviceRequest->kind->value,
                    'status' => $serviceRequest->status,
                    'requested_at' => $serviceRequest->getAttribute('requested_at')
                        ?->toIso8601String(),
                ],
            )->values()->all(),
        ]);
    }

    public function seat(
        Request $request,
        RestaurantTable $table,
    ): RedirectResponse {
        $this->authorizeRestaurant($request, $table);

        $validated = $request->validate([
            'party_size' => [
                'required',
                'integer',
                'min:1',
            ],
        ]);

        DB::transaction(function () use ($table, $validated): void {
            $lockedTable = RestaurantTable::query()
                ->lockForUpdate()
                ->findOrFail($table->id);

            if ($lockedTable->state !== TableState::FREE) {
                throw ValidationException::withMessages([
                    'table' => 'Only free tables can be seated.',
                ]);
            }

            $partySize = (int) $validated['party_size'];

            if ($partySize > (int) $lockedTable->seats) {
                throw ValidationException::withMessages([
                    'party_size' => 'Party size cannot exceed the table capacity.',
                ]);
            }

            $hasUnpaidOrders = Order::query()
                ->where('restaurant_id', $lockedTable->restaurant_id)
                ->where('table_id', $lockedTable->id)
                ->where('is_paid', false)
                ->where(
                    'status',
                    '!=',
                    OrderStatus::CANCELLED->value,
                )
                ->exists();

            if ($hasUnpaidOrders) {
                throw ValidationException::withMessages([
                    'table' => 'Cannot seat a table with an unpaid order.',
                ]);
            }

            $lockedTable->occupy($partySize);
        });

        return back()->with(
            'success',
            'Guests seated successfully.',
        );
    }

    public function free(
        Request $request,
        RestaurantTable $table,
    ): RedirectResponse {
        $this->authorizeRestaurant($request, $table);

        DB::transaction(function () use ($table): void {
            $lockedTable = RestaurantTable::query()
                ->lockForUpdate()
                ->findOrFail($table->id);

            $hasUnpaidOrders = Order::query()
                ->where('restaurant_id', $lockedTable->restaurant_id)
                ->where('table_id', $lockedTable->id)
                ->where('is_paid', false)
                ->where(
                    'status',
                    '!=',
                    OrderStatus::CANCELLED->value,
                )
                ->exists();

            if ($hasUnpaidOrders) {
                throw ValidationException::withMessages([
                    'table' => 'Cannot free this table while an unpaid bill exists.',
                ]);
            }

            TableSession::query()
                ->where('restaurant_table_id', $lockedTable->id)
                ->whereNull('closed_at')
                ->update([
                    'closed_at' => now(),
                ]);

            $lockedTable->free();
        });

        return back()->with(
            'success',
            'Table freed successfully.',
        );
    }

    public function payment(
        Request $request,
        RestaurantTable $table,
        SettlePayment $settlePayment,
    ): RedirectResponse {
        $this->authorizeRestaurant($request, $table);

        /** @var User $user */
        $user = $request->user();

        abort_unless(
            $user->can('take_payment'),
            403,
        );

        $validated = $request->validate([
            'order_id' => [
                'required',
                'integer',
            ],
            'method' => [
                'required',
                'string',
                Rule::in(['cash', 'pos']),
            ],
            'split_ways' => ['sometimes', 'required', 'integer', 'min:1', 'max:8'],
            'amount_minor' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
            ],
        ]);

        DB::transaction(function () use (
            $table,
            $user,
            $validated,
            $settlePayment,
        ): void {
            $lockedTable = RestaurantTable::query()
                ->lockForUpdate()
                ->findOrFail($table->id);

            $order = Order::query()
                ->where('restaurant_id', $lockedTable->restaurant_id)
                ->where('table_id', $lockedTable->id)
                ->whereKey($validated['order_id'])
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw ValidationException::withMessages([
                    'order_id' => 'Order not found for this table.',
                ]);
            }

            if ($order->is_paid) {
                throw ValidationException::withMessages([
                    'payment' => 'This order has already been paid.',
                ]);
            }

            if ($order->status !== OrderStatus::SERVED) {
                throw ValidationException::withMessages([
                    'payment' => 'The order must be served before payment.',
                ]);
            }

            if (isset($validated['split_ways'])) {
                $order->update([
                    'split_ways' => (int) $validated['split_ways'],
                ]);
            }

            $paidMinor = $this->paidMinor($order);

            $remainingMinor = $order->total->amount() - $paidMinor;

            if ($remainingMinor <= 0) {
                throw ValidationException::withMessages([
                    'payment' => 'This order has already been fully paid.',
                ]);
            }

            $amountMinor = array_key_exists(
                'amount_minor',
                $validated,
            )
                ? (int) $validated['amount_minor']
                : $remainingMinor;

            if ($amountMinor > $remainingMinor) {
                throw ValidationException::withMessages([
                    'amount_minor' => 'Payment exceeds the remaining balance.',
                ]);
            }

            // Reuse an existing pending offline payment when possible.
            // Never overwrite an online/card payment.

            $pendingPayment = $order->payments()
                ->where('status', PaymentStatus::PENDING->value)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($pendingPayment !== null) {
                if ($pendingPayment->method !== $validated['method']) {
                    throw ValidationException::withMessages([
                        'method' => 'Payment method does not match the order.',
                    ]);
                }

                if (
                    $amountMinor !== $pendingPayment->amount->amount()
                ) {
                    if (
                        $paidMinor > 0
                        && $pendingPayment->amount->amount()
                        !== $remainingMinor
                    ) {
                        throw ValidationException::withMessages([
                            'payment' => 'An existing pending payment must be settled as recorded.',
                        ]);
                    }

                    $pendingPayment->update([
                        'amount' => $amountMinor,
                    ]);
                }

                $payment = $pendingPayment;
            } else {
                $payment = $order->payments()->create([
                    'method' => $validated['method'],
                    'status' => PaymentStatus::PENDING->value,
                    'amount' => $amountMinor,
                    'tip_amount' => 0,
                ]);
            }

            $result = $settlePayment->handle(
                payment: $payment,
                takenBy: $user,
            );

            if (
                $result['failure'] !== null
                || $result['requires_action']
                || $result['payment']->status
                !== PaymentStatus::PAID->value
            ) {
                throw ValidationException::withMessages([
                    'payment' => 'Payment could not be completed.',
                ]);
            }

            // SettlePayment updates is_paid and paid_at.
            // Transition to PAID only when the entire bill is paid.

            $order->refresh();

            if ($order->is_paid) {
                $order->transitionTo(
                    OrderStatus::PAID,
                    $user,
                );
            }
        });

        return back()->with(
            'success',
            'Payment received successfully.',
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * @param  list<int>|null  $onlyTableIds  null for the whole floor
     * @return array<int, array<string, mixed>>
     */
    private function floorTables(int $restaurantId, ?array $onlyTableIds = null): array
    {
        $tables = RestaurantTable::query()
            ->where('restaurant_id', $restaurantId)
            ->when(
                $onlyTableIds !== null,
                fn ($query) => $query->whereIn('id', $onlyTableIds ?? []),
            )
            ->orderBy('sort_order')
            ->orderBy('number')
            ->get();

        $tableIds = $tables->pluck('id');

        $ordersByTable = Order::query()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('table_id', $tableIds)
            ->where('is_paid', false)
            ->where(
                'status',
                '!=',
                OrderStatus::CANCELLED->value,
            )
            ->with('payments')
            ->get()
            ->groupBy('table_id');

        $requestsByTable = ServiceRequest::query()
            ->whereIn('table_id', $tableIds)
            ->where('status', 'pending')
            ->get()
            ->groupBy('table_id');

        // Who has each table tonight (#27): the live shift's assignment.
        $now = now();

        $waiterByTable = DB::table('table_user')
            ->join('shifts', 'shifts.id', '=', 'table_user.shift_id')
            ->join('users', 'users.id', '=', 'table_user.user_id')
            ->whereIn('table_user.table_id', $tableIds)
            ->where('shifts.starts_at', '<=', $now)
            ->where('shifts.ends_at', '>=', $now)
            ->where('users.is_active', true)
            ->orderByDesc('shifts.starts_at')
            ->get(['table_user.table_id', 'users.id', 'users.name'])
            ->unique('table_id')
            ->keyBy('table_id');

        $restaurantCurrency = (string) (Restaurant::query()
            ->whereKey($restaurantId)
            ->value('currency') ?? 'USD');

        return $tables->map(function (RestaurantTable $table) use (
            $ordersByTable,
            $requestsByTable,
            $waiterByTable,
            $restaurantCurrency,
        ): array {
            $waiter = $waiterByTable->get($table->id);

            $orders = $ordersByTable->get(
                $table->id,
                collect(),
            );

            $requests = $requestsByTable->get(
                $table->id,
                collect(),
            );

            $openBillMinor = $orders->sum(
                fn (Order $order): int => max(
                    0,
                    $order->total->amount() - $this->paidMinor($order),
                ),
            );

            return [
                'id' => $table->id,
                'number' => $table->number,
                'state' => $table->state->value,
                'seats' => $table->seats,
                'party_size' => $table->party_size,
                'seated_at' => $table->seated_at?->toIso8601String(),

                'open_bill_minor' => $openBillMinor,
                'open_order_count' => $orders->count(),

                'pending_requests' => $requests->count(),

                'has_bill_request' => $requests->contains(
                    fn (ServiceRequest $serviceRequest): bool => $serviceRequest->kind->value === 'bill',
                ),

                'currency' => $orders->first()?->getAttribute('currency')
                    ?? $restaurantCurrency,

                'waiter' => $waiter === null ? null : [
                    'id' => (int) $waiter->id,
                    'name' => (string) $waiter->name,
                ],
            ];
        })->values()->all();
    }

    private function paidMinor(Order $order): int
    {
        $payments = $order->relationLoaded('payments')
            ? $order->payments
            : $order->payments()->get();

        return (int) $payments
            ->filter(
                fn (Payment $payment): bool => $payment->status === PaymentStatus::PAID->value,
            )
            ->sum(
                fn (Payment $payment): int => $payment->amount->amount(),
            );
    }

    private function authorizeRestaurant(
        Request $request,
        RestaurantTable $table,
    ): void {
        app(TableAccess::class)->authorize($request->user(), $table);
    }
}
