<?php

namespace App\Http\Controllers;

use App\Actions\Payments\SettlePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\User;
use App\Services\Floor\TableAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * POST /app/tables/{table}/payments: cash or POS taken at the table, the
 * whole bill or part of it, by whoever may take payment there.
 */
class TablePaymentController extends Controller
{
    public function store(
        Request $request,
        RestaurantTable $table,
        SettlePayment $settlePayment,
    ): RedirectResponse {
        app(TableAccess::class)->authorize($request->user(), $table);

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
                    'order_id' => __('messages.floor.order_not_found'),
                ]);
            }

            if ($order->is_paid) {
                throw ValidationException::withMessages([
                    'payment' => __('messages.floor.already_paid'),
                ]);
            }

            if ($order->status !== OrderStatus::SERVED) {
                throw ValidationException::withMessages([
                    'payment' => __('messages.floor.serve_first'),
                ]);
            }

            if (isset($validated['split_ways'])) {
                $order->update([
                    'split_ways' => (int) $validated['split_ways'],
                ]);
            }

            $paidMinor = $order->paidMinor();

            $remainingMinor = $order->total->amount() - $paidMinor;

            if ($remainingMinor <= 0) {
                throw ValidationException::withMessages([
                    'payment' => __('messages.payment.already_paid_full'),
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
                    'amount_minor' => __('messages.floor.exceeds_balance'),
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
                        'method' => __('messages.floor.method_mismatch'),
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
                            'payment' => __('messages.floor.pending_as_recorded'),
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
                    'payment' => __('messages.floor.payment_failed'),
                ]);
            }

            // SettlePayment updates is_paid and closes the order (PAID)
            // once the entire bill is paid.
        });

        return back();
    }
}
