<?php

namespace App\Http\Controllers;

use App\Actions\Orders\FinishPlacedOrder;
use App\Actions\Payments\SettlePayment;
use App\Models\Order;
use App\Support\GuestCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * POST /checkout/confirm: the guest finished 3-D Secure in the page; settle
 * the payment and finish the order.
 */
class CheckoutConfirmationController extends Controller
{
    public function __construct(
        private readonly GuestCheckout $checkout,
    ) {}

    public function store(
        Request $request,
        SettlePayment $settlePayment,
        FinishPlacedOrder $finish,
    ): RedirectResponse {
        $session = $this->checkout->tableSession($request);

        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'confirmed' => ['required', 'boolean'],
        ]);

        $pendingOrderId = $request->session()->get('checkout.pending_order_id');

        if (
            ! is_numeric($pendingOrderId)
            || (int) $pendingOrderId !== (int) $validated['order_id']
        ) {
            throw ValidationException::withMessages([
                'payment' => __('messages.checkout.challenge_invalid'),
            ]);
        }

        $order = Order::query()
            ->whereKey((int) $validated['order_id'])
            ->where('restaurant_id', $session->restaurant_id)
            ->where('table_session_id', $session->id)
            ->firstOrFail();

        $payment = $order->payments()
            ->latest('id')
            ->firstOrFail();

        if (! $validated['confirmed']) {
            throw ValidationException::withMessages([
                'payment' => __('messages.checkout.auth_not_completed'),
            ]);
        }

        $result = $settlePayment->handle(
            payment: $payment,
            payload: ['three_ds_complete' => true],
        );

        if ($result['failure'] !== null) {
            throw ValidationException::withMessages([
                'payment' => $result['failure']->message,
            ]);
        }

        if ($result['requires_action']) {
            throw ValidationException::withMessages([
                'payment' => __('messages.checkout.auth_required'),
            ]);
        }

        $request->session()->forget('checkout.pending_order_id');

        $finish->handle($order);

        return $this->checkout->statusRedirect($order->fresh() ?? $order);
    }
}
