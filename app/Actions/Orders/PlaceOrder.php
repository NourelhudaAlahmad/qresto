<?php

namespace App\Actions\Orders;

use App\Actions\Payments\PromotionValidator;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Payments\PaymentManager;
use App\Payments\ValueObjects\Failure;
use App\Support\Money;
use App\Support\OrderCodeGenerator;
use App\Support\OrderTotals;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        return DB::transaction(function () use (
            $cart,
            $method,
            $tipAmount,
            $promoCode,
            $paymentPayload,
            $splitWays,
            $idempotencyKey,
        ): array {
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
                    'cart' => 'Your cart is empty.',
                ]);
            }

            if ($tipAmount->currency() !== $cart->currency) {
                throw ValidationException::withMessages([
                    'tip' => 'The tip currency does not match the cart currency.',
                ]);
            }

            if ($splitWays < 1 || $splitWays > 8) {
                throw ValidationException::withMessages([
                    'split_ways' => 'Split ways must be between 1 and 8.',
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
                            'checkout' => 'The existing order does not have a payment.',
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
             * ORDER MORE
             *
             * If this table session already has an active unpaid order,
             * append the new cart lines to the same order/bill instead
             * of creating another order number.
             */
            $openOrder = Order::query()
                ->where(
                    'restaurant_id',
                    $cart->restaurant_id,
                )
                ->where(
                    'table_session_id',
                    $cart->table_session_id,
                )
                ->where('is_paid', false)
                ->whereNotIn('status', [
                    OrderStatus::PAID->value,
                    OrderStatus::CANCELLED->value,
                ])
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($openOrder !== null) {
                $payment = $openOrder
                    ->payments()
                    ->latest('id')
                    ->lockForUpdate()
                    ->first();

                if ($payment === null) {
                    throw ValidationException::withMessages([
                        'checkout' => 'The current order does not have a payment.',
                    ]);
                }

                if (
                    $payment->status
                    !== PaymentStatus::PENDING->value
                ) {
                    throw ValidationException::withMessages([
                        'checkout' => 'The current bill cannot accept more items.',
                    ]);
                }

                /*
                 * Cash/POS stay pending until settlement, so the amount
                 * may safely grow while the guest keeps ordering.
                 */
                if (! $this->isOfflineMethod($method)) {
                    throw ValidationException::withMessages([
                        'checkout' => 'Order more requires cash or POS while the current bill is open.',
                    ]);
                }

                $openOrderServicePct = (string) (
                    $openOrder->service_pct
                    ?? $servicePct
                );

                $combinedSubtotal = $openOrder
                    ->subtotal
                    ->add($subtotal);

                $combinedDiscountAmount = $openOrder
                    ->discount_amount
                    ->add($discountAmount);

                $combinedTipAmount = $openOrder
                    ->tip_amount
                    ->add($tipAmount);

                $combinedTotals = OrderTotals::calculate(
                    lines: [
                        [
                            'unit_price' => $combinedSubtotal,
                            'qty' => 1,
                        ],
                    ],
                    servicePct: $openOrderServicePct,
                    tipAmount: $combinedTipAmount,
                    discountAmount: $combinedDiscountAmount,
                );

                $combinedShareAmount = $this->calculateShareAmount(
                    total: $combinedTotals->total,
                    splitWays: $splitWays,
                );

                $this->copyCartLinesToOrder(
                    order: $openOrder,
                    lines: $lines,
                    currency: $cart->currency,
                );

                $openOrder->update([
                    'subtotal' => $combinedTotals->subtotal,
                    'service_pct' => $openOrderServicePct,
                    'service_amount' => $combinedTotals->serviceAmount,
                    'tip_amount' => $combinedTipAmount,
                    'discount_amount' => $combinedDiscountAmount,
                    'split_ways' => $splitWays,
                    'share_amount' => $combinedShareAmount,
                    'idempotency_key' => $idempotencyKey
                        ?? $openOrder->idempotency_key,
                    'total' => $combinedTotals->total,
                    'table_note' => $cart->note
                        ?? $openOrder->table_note,
                ]);

                $payment->update([
                    'method' => $method->value,
                    'amount' => $combinedTotals->total,
                    'tip_amount' => $combinedTipAmount,
                ]);

                $this->promotions->consume(
                    restaurantId: $cart->restaurant_id,
                    code: $promoCode,
                );

                return [
                    'order' => $openOrder->fresh(),
                    'payment' => $payment->fresh(),
                    'failure' => null,
                    'requires_action' => false,
                    'client_secret' => null,
                ];
            }

            /*
             * First order for this table session.
             */
            $serviceTimestamp = now();
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
                'status' => OrderStatus::PLACED,
                'placed_at' => $serviceTimestamp,
                'subtotal' => $totals->subtotal,
                'service_pct' => $servicePct,
                'service_amount' => $totals->serviceAmount,
                'tip_amount' => $tipAmount,
                'discount_amount' => $discountAmount,
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

            if ($this->isOfflineMethod($method)) {
                $this->promotions->consume(
                    restaurantId: $cart->restaurant_id,
                    code: $promoCode,
                );

                return [
                    'order' => $order->fresh(),
                    'payment' => $payment->fresh(),
                    'failure' => null,
                    'requires_action' => false,
                    'client_secret' => null,
                ];
            }

            $gateway = $this->payments->driver();

            $intent = $gateway->createIntent(
                order: $order,
                amount: $totals->total,
                meta: $paymentPayload,
            );

            $payment->update([
                'gateway' => (string) config(
                    'qresto.payments.driver',
                    'fake',
                ),
                'gateway_intent_id' => $intent->id,
                'gateway_status' => $intent->status,
                'requires_3ds' => $intent->requiresAction,
                'failure_reason' => $intent->failure?->message,
                'status' => match (true) {
                    $intent->failure !== null => PaymentStatus::FAILED->value,
                    $intent->requiresAction => PaymentStatus::REQUIRES_ACTION->value,
                    default => PaymentStatus::PAID->value,
                },
                'paid_at' => $intent->succeeded()
                    ? now()
                    : null,
            ]);

            if ($intent->failed()) {
                $order->events()->delete();

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

            $this->promotions->consume(
                restaurantId: $cart->restaurant_id,
                code: $promoCode,
            );

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
                'idempotency_key' => 'The idempotency key may not be greater than 100 characters.',
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
