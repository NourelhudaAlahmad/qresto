<?php

namespace App\Actions\Orders;

use App\Actions\Payments\PromotionValidator;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Cart;
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
    ): array {
        return DB::transaction(function () use (
            $cart,
            $method,
            $tipAmount,
            $promoCode,
            $paymentPayload,
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

            $subtotal = Money::fromMinor(0, $cart->currency);

            foreach ($lines as $line) {
                $subtotal = $subtotal->add($line->line_total);
            }

            $discountAmount = $this->promotions->validate(
                restaurantId: $cart->restaurant_id,
                code: $promoCode,
                subtotal: $subtotal,
            );

            $servicePct = (string) (
                $cart->restaurant->service_charge_pct
                ?? config('qresto.service_charge_pct', 0)
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

            $session = $cart->tableSession;

            $order = Order::query()->create([
                'restaurant_id' => $cart->restaurant_id,
                'table_id' => $session?->restaurant_table_id,
                'table_session_id' => $cart->table_session_id,
                'code' => $this->orderCodes->generate($cart->restaurant_id),
                'guest_name' => $session?->guest_name,
                'status' => OrderStatus::PLACED,
                'placed_at' => now(),
                'subtotal' => $totals->subtotal,
                'service_pct' => $servicePct,
                'service_amount' => $totals->serviceAmount,
                'tip_amount' => $tipAmount,
                'discount_amount' => $discountAmount,
                'total' => $totals->total,
                'is_paid' => false,
                'paid_at' => null,
                'table_note' => $cart->note,
                'currency' => $cart->currency,
            ]);

            foreach ($lines as $cartLine) {
                $orderLine = $order->lines()->create([
                    'menu_item_id' => $cartLine->menu_item_id,
                    'name_snapshot' => $cartLine->name_snapshot,
                    'unit_price' => $cartLine->unit_price,
                    'qty' => $cartLine->qty,
                    'line_total' => $cartLine->line_total,
                    'note' => $cartLine->note,
                ]);

                if (
                    $cartLine->menu_item_variant_id !== null
                    && $cartLine->variant_label_snapshot !== null
                ) {
                    $orderLine->options()->create([
                        'kind' => 'variant',
                        'label_snapshot' => $cartLine->variant_label_snapshot,
                        'price_delta' => $cartLine->variant_price_delta
                            ?? Money::fromMinor(0, $cart->currency),
                    ]);
                }

                foreach ($cartLine->addons as $addon) {
                    $orderLine->options()->create([
                        'kind' => 'addon',
                        'label_snapshot' => $addon->label_snapshot,
                        'price_delta' => $addon->price_delta,
                    ]);
                }
            }

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
                'paid_at' => $intent->succeeded() ? now() : null,
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

    private function isOfflineMethod(PaymentMethod $method): bool
    {
        return in_array(
            $method,
            [
                PaymentMethod::CASH,
                PaymentMethod::POS,
            ],
            true,
        );
    }
}
