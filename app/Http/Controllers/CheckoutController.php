<?php

namespace App\Http\Controllers;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Payments\PromotionValidator;
use App\Actions\Payments\SettlePayment;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\Order;
use App\Models\TableSession;
use App\Support\Money;
use App\Support\OrderTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutController extends Controller
{
    public function show(
        Request $request,
        PromotionValidator $promotions,
    ): Response|RedirectResponse {
        $session = $this->tableSession($request);
        $restaurant = $session->restaurant;
        $cart = $this->cartForSession($session);

        if ($cart === null) {
            return redirect()->route('cart');
        }

        $cart->load([
            'lines' => fn ($query) => $query
                ->whereNull('removed_at')
                ->with('addons')
                ->orderBy('id'),
        ]);

        if ($cart->lines->isEmpty()) {
            return redirect()->route('cart');
        }

        $currency = $restaurant->currency;
        $subtotal = $this->subtotal($cart, $currency);

        $promoCode = $request->session()->get('checkout.promo_code');

        try {
            $discountAmount = $promotions->validate(
                restaurantId: $restaurant->id,
                code: is_string($promoCode) ? $promoCode : null,
                subtotal: $subtotal,
            );
        } catch (ValidationException) {
            $request->session()->forget('checkout.promo_code');

            $promoCode = null;

            $discountAmount = Money::fromMinor(
                0,
                $currency,
            );
        }

        $servicePct = (string) (
            $restaurant->service_charge_pct
            ?? config('qresto.service_charge_pct', 0)
        );

        $zeroTip = Money::fromMinor(
            0,
            $currency,
        );

        $totals = OrderTotals::calculate(
            lines: [
                [
                    'unit_price' => $subtotal,
                    'qty' => 1,
                ],
            ],
            servicePct: $servicePct,
            tipAmount: $zeroTip,
            discountAmount: $discountAmount,
        );

        $enabledMethods = $this->enabledPaymentMethods();

        $tipPresets = $this->tipPresets();

        return Inertia::render('guest/checkout', [
            'restaurant' => [
                'id' => $restaurant->id,
                'name' => $restaurant->name,
                'currency' => $currency,
            ],

            'table' => [
                'id' => $session->table->id,
                'number' => $session->table->number,
            ],

            'cart' => [
                'id' => $cart->id,
                'subtotal' => $subtotal->jsonSerialize(),
                'service_pct' => $servicePct,
                'service_amount' => $totals->serviceAmount->jsonSerialize(),
                'discount_amount' => $discountAmount->jsonSerialize(),
                'total' => $totals->total->jsonSerialize(),
            ],

            'payment_methods' => $enabledMethods,
            'tip_presets' => $tipPresets,

            'promo' => [
                'code' => is_string($promoCode)
                    ? $promoCode
                    : null,
                'discount' => $discountAmount->jsonSerialize(),
            ],

            'gateway' => [
                'intent_id' => null,
                'status' => null,
                '3ds_required' => false,
            ],
        ]);
    }

    public function store(
        Request $request,
        PlaceOrder $placeOrder,
    ): RedirectResponse {
        $session = $this->tableSession($request);
        $cart = $this->cartForSession($session);

        if ($cart === null) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $enabledMethods = $this->enabledPaymentMethods();
        $tipPresets = $this->tipPresets();

        $validated = $request->validate([
            'method' => [
                'required',
                'string',
                Rule::in($enabledMethods),
            ],

            'tip_pct' => [
                'required',
                'numeric',
                Rule::in($tipPresets),
            ],

            'split_ways' => [
                'required',
                'integer',
                'min:1',
                'max:8',
            ],

            'idempotency_key' => [
                'required',
                'string',
                'max:100',
            ],

            'card_number' => [
                'nullable',
                'string',
                'max:30',
            ],

            'card_expiry' => [
                'nullable',
                'string',
                'max:10',
            ],

            'card_cvc' => [
                'nullable',
                'string',
                'max:4',
            ],
        ]);

        $method = PaymentMethod::from(
            $validated['method'],
        );

        if ($method === PaymentMethod::CARD) {
            $request->validate([
                'card_number' => [
                    'required',
                    'string',
                ],

                'card_expiry' => [
                    'required',
                    'string',
                ],

                'card_cvc' => [
                    'required',
                    'string',
                ],
            ]);
        }

        $cart->load([
            'restaurant',

            'lines' => fn ($query) => $query
                ->whereNull('removed_at')
                ->with('addons'),
        ]);

        if ($cart->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $subtotal = $this->subtotal(
            $cart,
            $cart->currency,
        );

        $tipPct = (float) $validated['tip_pct'];

        $tipAmount = Money::fromMinor(
            (int) round(
                $subtotal->amount() * ($tipPct / 100),
            ),
            $cart->currency,
        );

        $promoCode = $request->session()->get(
            'checkout.promo_code',
        );

        $paymentPayload = [];

        if ($method === PaymentMethod::CARD) {
            $paymentPayload = [
                'card_number' => preg_replace(
                    '/\s+/',
                    '',
                    (string) $validated['card_number'],
                ),

                'card_expiry' => (string) $validated['card_expiry'],

                'card_cvc' => (string) $validated['card_cvc'],
            ];
        }

        $result = $placeOrder->handle(
            cart: $cart,
            method: $method,
            tipAmount: $tipAmount,
            promoCode: is_string($promoCode)
                ? $promoCode
                : null,
            paymentPayload: $paymentPayload,
            splitWays: (int) $validated['split_ways'],
            idempotencyKey: (string) $validated['idempotency_key'],
        );

        if ($result['failure'] !== null) {
            /*
             * PlaceOrder deliberately persists failed attempts for the
             * payment abstraction. Checkout, however, must leave the
             * guest's order unplaced after a decline.
             */
            $failedOrder = $result['order'];

            $failedOrder->payments()->delete();

            $failedOrder->events()->delete();

            $failedOrder->lines()->each(
                fn ($line) => $line->options()->delete(),
            );

            $failedOrder->lines()->delete();

            $failedOrder->delete();

            throw ValidationException::withMessages([
                'card_cvc' => $result['failure']->message,
            ]);
        }

        $request->session()->forget(
            'checkout.promo_code',
        );

        if ($result['requires_action']) {
            $request->session()->put(
                'checkout.pending_order_id',
                $result['order']->id,
            );

            return redirect()
                ->route('checkout')
                ->with('checkout_3ds', [
                    'order_id' => $result['order']->id,
                    'payment_id' => $result['payment']->id,
                    'client_secret' => $result['client_secret'],
                ]);
        }

        return $this->statusRedirect(
            $result['order'],
        );
    }

    public function confirm(
        Request $request,
        SettlePayment $settlePayment,
    ): RedirectResponse {
        $session = $this->tableSession($request);

        $validated = $request->validate([
            'order_id' => [
                'required',
                'integer',
            ],

            'confirmed' => [
                'required',
                'boolean',
            ],
        ]);

        $pendingOrderId = $request->session()->get(
            'checkout.pending_order_id',
        );

        if (
            ! is_numeric($pendingOrderId)
            || (int) $pendingOrderId !== (int) $validated['order_id']
        ) {
            throw ValidationException::withMessages([
                'payment' => 'This payment challenge is no longer valid.',
            ]);
        }

        $order = Order::query()
            ->whereKey(
                (int) $validated['order_id'],
            )
            ->where(
                'restaurant_id',
                $session->restaurant_id,
            )
            ->where(
                'table_session_id',
                $session->id,
            )
            ->firstOrFail();

        $payment = $order->payments()
            ->latest('id')
            ->firstOrFail();

        if (! $validated['confirmed']) {
            throw ValidationException::withMessages([
                'payment' => 'The card authentication was not completed.',
            ]);
        }

        $result = $settlePayment->handle(
            payment: $payment,
            payload: [
                'three_ds_complete' => true,
            ],
        );

        if ($result['failure'] !== null) {
            throw ValidationException::withMessages([
                'payment' => $result['failure']->message,
            ]);
        }

        if ($result['requires_action']) {
            throw ValidationException::withMessages([
                'payment' => 'Card authentication is still required.',
            ]);
        }

        $request->session()->forget(
            'checkout.pending_order_id',
        );

        return $this->statusRedirect(
            $order->fresh(),
        );
    }

    public function applyPromo(
        Request $request,
        PromotionValidator $promotions,
    ): RedirectResponse {
        $session = $this->tableSession($request);
        $cart = $this->cartForSession($session);

        if ($cart === null) {
            throw ValidationException::withMessages([
                'promo_code' => 'Your cart is empty.',
            ]);
        }

        $validated = $request->validate([
            'promo_code' => [
                'required',
                'string',
                'max:100',
            ],
        ]);

        $cart->load([
            'lines' => fn ($query) => $query
                ->whereNull('removed_at')
                ->with('addons'),
        ]);

        if ($cart->lines->isEmpty()) {
            throw ValidationException::withMessages([
                'promo_code' => 'Your cart is empty.',
            ]);
        }

        $subtotal = $this->subtotal(
            $cart,
            $cart->currency,
        );

        $code = trim(
            $validated['promo_code'],
        );

        $promotions->validate(
            restaurantId: $session->restaurant_id,
            code: $code,
            subtotal: $subtotal,
        );

        $request->session()->put(
            'checkout.promo_code',
            $code,
        );

        return redirect()->route('checkout');
    }

    public function removePromo(
        Request $request,
    ): RedirectResponse {
        $request->session()->forget(
            'checkout.promo_code',
        );

        return redirect()->route('checkout');
    }

    private function tableSession(
        Request $request,
    ): TableSession {
        /** @var TableSession|null $session */
        $session = $request->attributes->get(
            'tableSession',
        );

        abort_if(
            $session === null,
            403,
        );

        return $session;
    }

    private function cartForSession(
        TableSession $session,
    ): ?Cart {
        return Cart::query()
            ->where(
                'restaurant_id',
                $session->restaurant_id,
            )
            ->where(
                'table_session_id',
                $session->id,
            )
            ->first();
    }

    /**
     * @return list<string>
     */
    private function enabledPaymentMethods(): array
    {
        $configured = config(
            'qresto.payments.enabled_methods',
            ['card', 'wallet', 'cash', 'pos'],
        );

        if (! is_array($configured)) {
            $configured = [
                'card',
                'wallet',
                'cash',
                'pos',
            ];
        }

        return array_values(
            array_filter(
                $configured,
                static fn (mixed $method): bool => is_string($method),
            ),
        );
    }

    /**
     * @return list<float>
     */
    private function tipPresets(): array
    {
        $configured = config(
            'qresto.payments.tip_presets',
            [0, 10, 12.5, 15],
        );

        if (! is_array($configured)) {
            $configured = [
                0,
                10,
                12.5,
                15,
            ];
        }

        return array_values(
            array_map(
                static fn (mixed $tip): float => (float) $tip,
                $configured,
            ),
        );
    }

    private function subtotal(
        Cart $cart,
        string $currency,
    ): Money {
        $subtotal = Money::fromMinor(
            0,
            $currency,
        );

        foreach ($cart->lines as $line) {
            if ($line->removed_at !== null) {
                continue;
            }

            $subtotal = $subtotal->add(
                $line->line_total,
            );
        }

        return $subtotal;
    }

    private function statusRedirect(
        Order $order,
    ): RedirectResponse {
        /*
         * #19 owns the final guest status screen. Until that route exists,
         * keep the guest inside the application and return to the menu with
         * the placed order id. #19 can replace this single redirect.
         */
        return redirect()
            ->route('menu')
            ->with(
                'placed_order_id',
                $order->id,
            );
    }
}
