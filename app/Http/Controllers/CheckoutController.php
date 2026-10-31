<?php

namespace App\Http\Controllers;

use App\Actions\Orders\PlaceOrder;
use App\Actions\Payments\PromotionValidator;
use App\Actions\Payments\SettlePayment;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\TableState;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Payments\PaymentManager;
use App\Support\Money;
use App\Support\OrderTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
                // A payment waiting on 3-D Secure: shown in-page, and still
                // there after a reload.
                ...$this->pendingAuthentication($request),
                // #35: which driver the page talks to, and Stripe's public
                // key when it is Stripe. Secret keys never leave the server.
                'driver' => $this->driver(),
                'publishable_key' => $this->driver() === 'stripe'
                    ? config('qresto.payments.stripe.key')
                    : null,
                'country' => (string) config('qresto.payments.stripe.country', 'US'),
            ],
        ]);
    }

    public function store(
        Request $request,
        PlaceOrder $placeOrder,
    ): RedirectResponse {
        $session = $this->tableSession($request);

        // A repeat of a checkout that already went through (a double tap,
        // a retried request) lands on that order. The cart is empty by
        // then, so this has to come before the empty-cart check (#37).
        $key = $request->input('idempotency_key');

        if (is_string($key) && $key !== '') {
            $existing = Order::query()
                ->where('table_session_id', $session->id)
                ->where('idempotency_key', $key)
                ->first();

            if ($existing !== null) {
                return $this->statusRedirect($existing);
            }
        }

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

        // #35: with Stripe, card details are tokenised in the browser and
        // only a PaymentMethod id arrives. Raw card fields are refused, so a
        // card number can never reach this server again.
        if ($this->driver() === 'stripe' && in_array($method, [PaymentMethod::CARD, PaymentMethod::WALLET], true)) {
            $request->validate([
                'payment_method' => ['required', 'string', 'max:255', 'regex:/^pm_[A-Za-z0-9_]+$/'],
                'card_number' => ['prohibited'],
                'card_expiry' => ['prohibited'],
                'card_cvc' => ['prohibited'],
            ]);
        } elseif ($method === PaymentMethod::CARD) {
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

        if ($this->driver() === 'stripe' && in_array($method, [PaymentMethod::CARD, PaymentMethod::WALLET], true)) {
            $paymentPayload = [
                'payment_method' => (string) $request->input('payment_method'),
                'idempotency_key' => (string) $validated['idempotency_key'],
            ];
        } elseif ($method === PaymentMethod::CARD) {
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

        $this->afterOrderPlaced($result['order']);

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

        $this->afterOrderPlaced($order);

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
        return redirect()->route(
            'guest.orders.status',
            $order,
        );
    }

    private function driver(): string
    {
        return (string) config('qresto.payments.driver', 'fake');
    }

    /**
     * The guest's payment that is waiting on 3-D Secure, if any.
     *
     * @return array<string, mixed>
     */
    private function pendingAuthentication(Request $request): array
    {
        $orderId = $request->session()->get('checkout.pending_order_id');

        if (! is_numeric($orderId)) {
            return [];
        }

        $session = $this->tableSession($request);

        $payment = Payment::query()
            ->whereHas('order', fn ($query) => $query
                ->whereKey((int) $orderId)
                ->where('table_session_id', $session->id))
            ->where('status', PaymentStatus::REQUIRES_ACTION->value)
            ->latest('id')
            ->first();

        if ($payment === null || $payment->gateway_intent_id === null) {
            return [];
        }

        /** @var array<string, mixed>|null $flash */
        $flash = $request->session()->get('checkout_3ds');

        // The client secret is not stored; after a reload, ask the gateway.
        $clientSecret = is_array($flash) && ($flash['payment_id'] ?? null) === $payment->id
            ? ($flash['client_secret'] ?? null)
            : app(PaymentManager::class)
                ->driver($payment->gateway ?: null)
                ->status($payment->gateway_intent_id)
                ->clientSecret;

        return [
            'intent_id' => $payment->gateway_intent_id,
            'status' => 'requires_action',
            '3ds_required' => true,
            'order_id' => (int) $payment->order_id,
            'payment_id' => (int) $payment->id,
            'client_secret' => $clientSecret,
        ];
    }

    /**
     * Once an order is placed (paid, deferred, or confirmed after 3-D
     * Secure), found by the service journey (#37):
     *
     * - its cart is emptied, so the guest's next visit starts fresh and a
     *   second checkout cannot order the same dishes again;
     * - its table shows as ordered on the floor, so a table with guests
     *   and an open bill never looks free.
     *
     * A failed payment never gets here: its cart stays for another try.
     */
    private function afterOrderPlaced(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $cart = Cart::query()
                ->where('table_session_id', $order->table_session_id)
                ->first();

            if ($cart !== null) {
                $cart->lines()->each(function ($line): void {
                    $line->addons()->delete();
                    $line->delete();
                });

                $cart->forceFill(['note' => null])->save();
            }

            $table = $order->table_id === null
                ? null
                : RestaurantTable::query()->withoutGlobalScopes()->find($order->table_id);

            if ($table !== null && in_array($table->state, [TableState::FREE, TableState::SEATED], true)) {
                $table->forceFill([
                    'state' => TableState::ORDERED,
                    'seated_at' => $table->seated_at ?? now(),
                ])->save();
            }
        });
    }
}
