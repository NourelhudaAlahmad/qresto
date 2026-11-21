<?php

namespace App\Actions\Orders;

use App\Actions\Payments\PromotionValidator;
use App\Actions\Payments\SettlePayment;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TableSession;
use App\Payments\PaymentManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * A guest who leaves the 3-D Secure step and checks out again must not
 * end up with two orders for the same cart.
 *
 * Before a new checkout, each of the session's orders still waiting on
 * 3-D Secure is cancelled at the gateway first, so it can never be paid
 * later with no order behind it:
 *
 * - if it turns out it was paid meanwhile, that order is finished and the
 *   guest goes to it instead of paying twice;
 * - if it was cancelled, the order is discarded and its promo use given
 *   back, and the new checkout goes ahead;
 * - if the gateway cannot say, nothing is placed: the guest tries again.
 */
final class ResolveAbandonedCheckout
{
    public const PAID = 'paid';

    public const DISCARDED = 'discarded';

    public const UNDECIDED = 'undecided';

    public function __construct(
        private readonly PaymentManager $payments,
        private readonly SettlePayment $settle,
        private readonly FinishPlacedOrder $finish,
        private readonly PromotionValidator $promotions,
    ) {}

    /**
     * @return Order|null the order that turned out to be paid, if any
     */
    public function handle(TableSession $session): ?Order
    {
        $waiting = Order::query()
            ->where('table_session_id', $session->id)
            ->where('is_paid', false)
            ->whereHas('payments', fn ($payments) => $payments->where('status', PaymentStatus::REQUIRES_ACTION->value))
            ->with('payments')
            ->get();

        foreach ($waiting as $order) {
            $outcome = $this->resolve($order);

            if ($outcome === self::PAID) {
                return $order->fresh();
            }

            if ($outcome === self::UNDECIDED) {
                throw ValidationException::withMessages([
                    'payment' => __('messages.checkout.previous_payment_pending'),
                ]);
            }
        }

        return null;
    }

    /**
     * Cancel one order's unfinished card payment at the gateway, then finish
     * the order if it turned out paid, or discard it if it was cancelled.
     * A payment the gateway never answered (no intent) moved no money and
     * is simply discarded.
     *
     * @return self::PAID|self::DISCARDED|self::UNDECIDED
     */
    public function resolve(Order $order): string
    {
        $payment = Order::unfinishedOnlinePayment($order->payments()->getQuery())
            ->latest('id')
            ->first();

        if (! $payment instanceof Payment) {
            return self::UNDECIDED;
        }

        if ($payment->gateway_intent_id === null) {
            $this->promotions->release($order->restaurant_id, $order->promo_code);
            $this->discard($order);

            return self::DISCARDED;
        }

        $result = $this->payments
            ->driver($payment->gateway ?: null)
            ->cancel((string) $payment->gateway_intent_id);

        if ($result->succeeded()) {
            $this->settle->handle($payment);
            $this->finish->handle($order);

            return self::PAID;
        }

        if ($result->status !== 'canceled') {
            return self::UNDECIDED;
        }

        $this->promotions->release($order->restaurant_id, $order->promo_code);
        $this->discard($order);

        return self::DISCARDED;
    }

    /**
     * Remove an order that was never placed, with everything under it.
     */
    public function discard(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $order->payments()->delete();
            $order->events()->delete();
            $order->lines()->each(fn ($line) => $line->options()->delete());
            $order->lines()->delete();
            $order->delete();
        });
    }
}
