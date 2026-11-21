<?php

namespace App\Actions\Orders;

use App\Actions\Payments\PromotionValidator;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\CartLineAddon;
use App\Models\MenuItem;
use App\Models\MenuItemAddon;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentManager;
use App\Payments\ValueObjects\Failure;
use App\Services\Floor\TableAccess;
use App\Support\Money;
use App\Support\OrderCodeGenerator;
use App\Support\OrderTotals;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class PlaceOrder
{
    public function __construct(
        private readonly PaymentManager $payments,
        private readonly PromotionValidator $promotions,
        private readonly OrderCodeGenerator $orderCodes,
    ) {}

    /**
     * @param  array<string, mixed>  $paymentPayload
     * @return array{
     *     order: Order,
     *     payment: Payment,
     *     failure: Failure|null,
     *     requires_action: bool,
     *     client_secret: string|null
     * }
     */
    public function handle(
        Cart $cart,
        PaymentMethod $method,
        Money $tipAmount,
        ?string $promoCode = null,
        array $paymentPayload = [],
        int $splitWays = 1,
        ?string $idempotencyKey = null,
    ): array {
        $order = null;
        $payment = null;

        /*
         * The order is written and committed first, with its payment
         * pending and no intent yet; the gateway is called only after.
         * Calling Stripe inside the transaction held row locks for the
         * length of a network call, rolled the order away if anything
         * failed after a card was charged, and hid the payment from a
         * webhook that arrived before the commit.
         */
        $result = DB::transaction(function () use (
            $cart,
            $method,
            $tipAmount,
            $promoCode,
            $splitWays,
            $idempotencyKey,
            &$order,
            &$payment,
        ): ?array {
            $cart->load([
                'restaurant',
                'tableSession.table',
                'lines.addons',
            ]);

            $lines = $cart->lines
                ->whereNull('removed_at')
                ->values();

            if ($lines->isEmpty()) {
                throw ValidationException::withMessages([
                    'cart' => __('messages.cart.empty'),
                ]);
            }

            if ($tipAmount->currency() !== $cart->currency) {
                throw ValidationException::withMessages([
                    'tip' => __('messages.checkout.tip_currency'),
                ]);
            }

            if ($splitWays < 1 || $splitWays > 8) {
                throw ValidationException::withMessages([
                    'split_ways' => __('messages.checkout.split_ways'),
                ]);
            }

            $idempotencyKey = $this->normalizeIdempotencyKey(
                $idempotencyKey,
            );

            if ($idempotencyKey !== null) {
                $existingOrder = Order::query()
                    ->where(
                        'restaurant_id',
                        $cart->restaurant_id,
                    )
                    ->where(
                        'idempotency_key',
                        $idempotencyKey,
                    )
                    ->lockForUpdate()
                    ->first();

                if ($existingOrder !== null) {
                    $existingPayment = $existingOrder
                        ->payments()
                        ->latest('id')
                        ->first();

                    if ($existingPayment === null) {
                        throw ValidationException::withMessages([
                            'checkout' => __('messages.checkout.existing_order_no_payment'),
                        ]);
                    }

                    return [
                        'order' => $existingOrder,
                        'payment' => $existingPayment,
                        'failure' => null,
                        'requires_action' => $existingPayment->status
                            === PaymentStatus::REQUIRES_ACTION->value,
                        'client_secret' => null,
                    ];
                }
            }

            $this->ensureLinesCanStillBeOrdered(
                restaurantId: $cart->restaurant_id,
                lines: $lines,
            );

            $subtotal = Money::fromMinor(
                0,
                $cart->currency,
            );

            foreach ($lines as $line) {
                $subtotal = $subtotal->add(
                    $line->line_total,
                );
            }

            $discountAmount = $this->promotions->validate(
                restaurantId: $cart->restaurant_id,
                code: $promoCode,
                subtotal: $subtotal,
            );

            $servicePct = (string) (
                $cart->restaurant->service_charge_pct
                ?? config(
                    'qresto.service_charge_pct',
                    0,
                )
            );

            $totalLines = $lines
                ->map(fn ($line): array => [
                    'unit_price' => $line->line_total,
                    'qty' => 1,
                ])
                ->all();

            $totals = OrderTotals::calculate(
                lines: $totalLines,
                servicePct: $servicePct,
                tipAmount: $tipAmount,
                discountAmount: $discountAmount,
            );

            $shareAmount = $this->calculateShareAmount(
                total: $totals->total,
                splitWays: $splitWays,
            );

            $session = $cart->tableSession;

            /*
             * ORDER MORE (#50)
             *
             * Every round is its own order: its own code, its own kitchen
             * ticket and its own payment. A later round never joins an
             * earlier one, so a dessert ordered after the main course was
             * served still reaches the pass, and it can be paid by card.
             * The table's bill is the sum of its session's unpaid orders,
             * which the floor and the guest status screen both show.
             */
            // The service day is the restaurant's own date, the one every
            // report, dashboard and order-code sequence reads.
            $serviceTimestamp = $cart->restaurant->localTime();
            $serviceDate = $serviceTimestamp->toDateString();

            $order = Order::query()->create([
                'restaurant_id' => $cart->restaurant_id,
                'table_id' => $session?->restaurant_table_id,
                'table_session_id' => $cart->table_session_id,
                'service_date' => $serviceDate,
                'code' => $this->orderCodes->generate(
                    $cart->restaurant_id,
                    $serviceTimestamp,
                ),
                'guest_name' => $session?->guest_name,
                // The waiter who has this table tonight (#37). Without it a
                // guest's order reached nobody's "My orders".
                'assigned_user_id' => $session?->restaurant_table_id === null
                    ? null
                    : app(TableAccess::class)->waiterTonight((int) $session->restaurant_table_id),
                'status' => OrderStatus::PLACED,
                'placed_at' => now(),
                'subtotal' => $totals->subtotal,
                'service_pct' => $servicePct,
                'service_amount' => $totals->serviceAmount,
                'tip_amount' => $tipAmount,
                'discount_amount' => $discountAmount,
                'promo_code' => is_string($promoCode) && trim($promoCode) !== '' ? strtoupper(trim($promoCode)) : null,
                'split_ways' => $splitWays,
                'share_amount' => $shareAmount,
                'idempotency_key' => $idempotencyKey,
                'total' => $totals->total,
                'is_paid' => false,
                'paid_at' => null,
                'table_note' => $cart->note,
                'currency' => $cart->currency,
            ]);

            $this->copyCartLinesToOrder(
                order: $order,
                lines: $lines,
                currency: $cart->currency,
            );

            $payment = $order->payments()->create([
                'method' => $method->value,
                'status' => PaymentStatus::PENDING->value,
                'amount' => $totals->total,
                'tip_amount' => $tipAmount,
                'gateway' => null,
                'gateway_intent_id' => null,
                'gateway_status' => null,
                'requires_3ds' => false,
                'failure_reason' => null,
                'paid_at' => null,
                'refunded_amount' => Money::fromMinor(
                    0,
                    $cart->currency,
                ),
            ]);

            $order->events()->create([
                'from_status' => null,
                'to_status' => OrderStatus::PLACED->value,
                'actor_id' => null,
                'actor_kind' => 'guest',
                'occurred_at' => now(),
                'meta' => [
                    'payment_method' => $method->value,
                    'split_ways' => $splitWays,
                ],
            ]);

            // Claim the promo use before any money moves. If the code ran
            // out meanwhile this throws and the whole order rolls back.
            $this->promotions->consume(
                restaurantId: $cart->restaurant_id,
                code: $promoCode,
            );

            if ($this->isOfflineMethod($method)) {
                return [
                    'order' => $order->fresh(),
                    'payment' => $payment->fresh(),
                    'failure' => null,
                    'requires_action' => false,
                    'client_secret' => null,
                ];
            }

            // A card or wallet: charged below, once this is committed.
            return null;
        });

        if ($result !== null) {
            return $result;
        }

        if (! $order instanceof Order || ! $payment instanceof Payment) {
            throw new LogicException('An online order must be placed before it is charged.');
        }

        return $this->charge($order, $payment, $paymentPayload, $promoCode);
    }

    /**
     * Ask the gateway for the money, outside any transaction, then record
     * what it answered.
     *
     * @param  array<string, mixed>  $paymentPayload
     * @return array{
     *     order: Order,
     *     payment: Payment,
     *     failure: Failure|null,
     *     requires_action: bool,
     *     client_secret: string|null
     * }
     */
    private function charge(
        Order $order,
        Payment $payment,
        array $paymentPayload,
        ?string $promoCode,
    ): array {
        try {
            $intent = $this->payments->driver()->createIntent(
                order: $order,
                amount: $payment->amount,
                meta: $paymentPayload,
            );
        } catch (Throwable $exception) {
            // The gateway answered nothing usable: no money moved, so the
            // order is taken back, as the old single transaction did.
            $this->promotions->release($order->restaurant_id, $promoCode);
            app(ResolveAbandonedCheckout::class)->discard($order);

            throw $exception;
        }

        return DB::transaction(function () use ($order, $payment, $intent, $promoCode): array {
            $payment->update([
                'gateway' => (string) config(
                    'qresto.payments.driver',
                    'fake',
                ),
                'gateway_intent_id' => $intent->id,
                'gateway_status' => $intent->status,
                'requires_3ds' => $intent->requiresAction,
                'failure_reason' => $intent->failure?->message,
                // Only a succeeded intent is money in hand. A processing one
                // stays pending until the gateway's webhook settles it (#35).
                'status' => match (true) {
                    $intent->failure !== null => PaymentStatus::FAILED->value,
                    $intent->requiresAction => PaymentStatus::REQUIRES_ACTION->value,
                    $intent->succeeded() => PaymentStatus::PAID->value,
                    default => PaymentStatus::PENDING->value,
                },
                'paid_at' => $intent->succeeded()
                    ? now()
                    : null,
            ]);

            if ($intent->failed()) {
                $order->events()->delete();

                $this->promotions->release(
                    restaurantId: $order->restaurant_id,
                    code: $promoCode,
                );

                return [
                    'order' => $order->fresh(),
                    'payment' => $payment->fresh(),
                    'failure' => $intent->failure,
                    'requires_action' => false,
                    'client_secret' => null,
                ];
            }

            if ($intent->succeeded()) {
                $order->update([
                    'is_paid' => true,
                    'paid_at' => now(),
                ]);
            }

            return [
                'order' => $order->fresh(),
                'payment' => $payment->fresh(),
                'failure' => null,
                'requires_action' => $intent->requiresAction,
                'client_secret' => $intent->clientSecret,
            ];
        });
    }

    /**
     * A dish is checked when it enters the cart, but the cart can sit for a
     * while: the kitchen may mark it sold out, take it off the schedule or
     * delete it meanwhile. Refuse the order rather than send the kitchen a
     * dish it cannot make.
     *
     * @param  Collection<int, CartLine>  $lines
     */
    private function ensureLinesCanStillBeOrdered(
        int $restaurantId,
        Collection $lines,
    ): void {
        $orderableItemIds = MenuItem::query()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('id', $lines->pluck('menu_item_id')->unique()->all())
            ->availableNow()
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $unavailableAddonIds = MenuItemAddon::query()
            ->whereIn(
                'id',
                $lines->flatMap(fn (CartLine $line) => $line->addons->pluck('menu_item_addon_id'))->unique()->all(),
            )
            ->where('is_available', false)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($lines as $line) {
            $itemGone = ! in_array((int) $line->menu_item_id, $orderableItemIds, true);

            $addonGone = $line->addons->contains(
                fn (CartLineAddon $addon): bool => in_array((int) $addon->menu_item_addon_id, $unavailableAddonIds, true),
            );

            if ($itemGone || $addonGone) {
                throw ValidationException::withMessages([
                    'cart' => __('messages.checkout.item_unavailable', [
                        'dish' => $line->name_snapshot,
                    ]),
                ]);
            }
        }
    }

    /**
     * Copy cart lines and their options into an order.
     *
     * @param  iterable<int, mixed>  $lines
     */
    private function copyCartLinesToOrder(
        Order $order,
        iterable $lines,
        string $currency,
    ): void {
        foreach ($lines as $cartLine) {
            $orderLine = $order->lines()->create([
                'menu_item_id' => $cartLine->menu_item_id,
                'name_snapshot' => $cartLine->name_snapshot,
                'unit_price' => $cartLine->unit_price,
                'qty' => $cartLine->qty,
                'line_total' => $cartLine->line_total,
                'note' => $cartLine->note,
                // Snapshot the kitchen station with the name and price
                // (#33), so moving a dish later does not reroute old tickets.
                'station' => $this->stationFor((int) $cartLine->menu_item_id),
            ]);

            if (
                $cartLine->menu_item_variant_id !== null
                && $cartLine->variant_label_snapshot !== null
            ) {
                $orderLine->options()->create([
                    'kind' => 'variant',
                    'label_snapshot' => $cartLine
                        ->variant_label_snapshot,
                    'price_delta' => $cartLine
                        ->variant_price_delta
                        ?? Money::fromMinor(
                            0,
                            $currency,
                        ),
                ]);
            }

            foreach ($cartLine->addons as $addon) {
                $orderLine->options()->create([
                    'kind' => 'addon',
                    'label_snapshot' => $addon
                        ->label_snapshot,
                    'price_delta' => $addon->price_delta,
                ]);
            }
        }
    }

    private function calculateShareAmount(
        Money $total,
        int $splitWays,
    ): Money {
        if ($splitWays === 1) {
            return $total;
        }

        return Money::fromMinor(
            (int) ceil(
                $total->amount() / $splitWays,
            ),
            $total->currency(),
        );
    }

    private function normalizeIdempotencyKey(
        ?string $key,
    ): ?string {
        if ($key === null) {
            return null;
        }

        $key = trim($key);

        if ($key === '') {
            return null;
        }

        if (strlen($key) > 100) {
            throw ValidationException::withMessages([
                'idempotency_key' => __('messages.checkout.idempotency_key'),
            ]);
        }

        return $key;
    }

    private function isOfflineMethod(
        PaymentMethod $method,
    ): bool {
        return in_array(
            $method,
            [
                PaymentMethod::CASH,
                PaymentMethod::POS,
            ],
            true,
        );
    }

    /**
     * The kitchen station of a dish, from its category (#33).
     */
    private function stationFor(int $menuItemId): ?string
    {
        $item = MenuItem::withTrashed()
            ->with('category:id,station')
            ->whereKey($menuItemId)
            ->first();

        return $item?->category?->station;
    }
}
