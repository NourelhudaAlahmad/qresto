<?php

namespace App\Http\Controllers;

use App\Actions\Orders\FinishPlacedOrder;
use App\Actions\Orders\PlaceOrder;
use App\Actions\Orders\ResolveAbandonedCheckout;
use App\Actions\Payments\PromotionValidator;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentManager;
use App\Support\GuestCheckout;
use App\Support\Money;
use App\Support\OrderTotals;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The checkout screen and placing the order. Confirming 3-D Secure and the
 * promo code have their own controllers; GuestCheckout holds what they share.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly GuestCheckout $checkout,
    ) {}

    public function show(
        Request $request,
        PromotionValidator $promotions,
    ): Response|RedirectResponse {
        $session = $this->checkout->tableSession($request);
        $restaurant = $session->restaurant;
        $cart = $this->checkout->cartFor($session);

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
        $subtotal = $this->checkout->subtotal($cart, $currency);

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

        $enabledMethods = $this->checkout->enabledPaymentMethods();

        $tipPresets = $this->checkout->tipPresets();

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
                'driver' => $this->checkout->driver(),
                'publishable_key' => $this->checkout->driver() === 'stripe'
                    ? config('qresto.payments.stripe.key')
                    : null,
                'country' => (string) config('qresto.payments.stripe.country', 'US'),
            ],

            // Rounds this sitting already placed: a new checkout adds one more
            // order to the same table bill (#50).
            'earlier_rounds' => Order::query()
                ->where('table_session_id', $session->id)
                ->where('status', '!=', OrderStatus::CANCELLED->value)
                ->count(),

            'translations' => trans('checkout'),
        ]);
    }

    public function store(
        Request $request,
        PlaceOrder $placeOrder,
        ResolveAbandonedCheckout $abandoned,
    ): RedirectResponse {
        $session = $this->checkout->tableSession($request);

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
                return $this->checkout->statusRedirect($existing);
            }
        }

        // A checkout left at 3-D Secure is settled or cancelled first, so
        // this one never becomes a second order for the same cart.
        $alreadyPaid = $abandoned->handle($session);

        if ($alreadyPaid !== null) {
            $request->session()->forget('checkout.pending_order_id');

            return $this->checkout->statusRedirect($alreadyPaid);
        }

        $cart = $this->checkout->cartFor($session);

        if ($cart === null) {
            throw ValidationException::withMessages([
                'cart' => __('messages.cart.empty'),
            ]);
        }

        $enabledMethods = $this->checkout->enabledPaymentMethods();
        $tipPresets = $this->checkout->tipPresets();

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
        if ($this->checkout->driver() === 'stripe' && in_array($method, [PaymentMethod::CARD, PaymentMethod::WALLET], true)) {
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
                'cart' => __('messages.cart.empty'),
            ]);
        }

        $subtotal = $this->checkout->subtotal(
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

        if ($this->checkout->driver() === 'stripe' && in_array($method, [PaymentMethod::CARD, PaymentMethod::WALLET], true)) {
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
            $abandoned->discard($result['order']);

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

        return $this->checkout->statusRedirect(
            $result['order'],
        );
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

        $session = $this->checkout->tableSession($request);

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

    private function afterOrderPlaced(Order $order): void
    {
        app(FinishPlacedOrder::class)->handle($order);
    }
}
