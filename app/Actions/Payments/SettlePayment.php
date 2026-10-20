<?php

namespace App\Actions\Payments;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Payments\ValueObjects\Failure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SettlePayment
{
    public function __construct(
        private readonly PaymentManager $payments,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     payment: Payment,
     *     failure: Failure|null,
     *     requires_action: bool,
     *     client_secret: string|null
     * }
     */
    public function handle(
        Payment $payment,
        ?User $takenBy = null,
        array $payload = [],
    ): array {
        return DB::transaction(function () use (
            $payment,
            $takenBy,
            $payload,
        ): array {
            $payment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->id);

            $order = $payment->order()
                ->lockForUpdate()
                ->firstOrFail();

            $payment->setRelation('order', $order);

            if ($payment->status === PaymentStatus::PAID->value) {
                return [
                    'payment' => $payment,
                    'failure' => null,
                    'requires_action' => false,
                    'client_secret' => null,
                ];
            }

            $this->validatePaymentAmount($payment);

            $method = PaymentMethod::from($payment->method);

            if ($this->isOfflineMethod($method)) {
                if ($takenBy === null) {
                    throw ValidationException::withMessages([
                        'payment' => 'A staff member is required to settle this payment.',
                    ]);
                }

                $paidAt = now();

                $payment->update([
                    'status' => PaymentStatus::PAID->value,
                    'taken_by' => $takenBy->id,
                    'paid_at' => $paidAt,
                    'requires_3ds' => false,
                    'failure_reason' => null,
                ]);

                $this->syncOrderPaymentState($payment);

                return [
                    'payment' => $payment->fresh(),
                    'failure' => null,
                    'requires_action' => false,
                    'client_secret' => null,
                ];
            }

            if ($payment->gateway_intent_id === null) {
                throw ValidationException::withMessages([
                    'payment' => 'This payment does not have a gateway intent.',
                ]);
            }

            $gateway = $this->payments->driver(
                $payment->gateway ?: null,
            );

            $result = $gateway->confirm(
                intentId: $payment->gateway_intent_id,
                payload: $payload,
            );

            if ($result->failed()) {
                $payment->update([
                    'status' => PaymentStatus::FAILED->value,
                    'gateway_status' => $result->status,
                    'requires_3ds' => false,
                    'failure_reason' => $result->failure?->message,
                ]);

                return [
                    'payment' => $payment->fresh(),
                    'failure' => $result->failure,
                    'requires_action' => false,
                    'client_secret' => null,
                ];
            }

            if ($result->requiresAction) {
                $payment->update([
                    'status' => PaymentStatus::REQUIRES_ACTION->value,
                    'gateway_status' => $result->status,
                    'requires_3ds' => true,
                    'failure_reason' => null,
                ]);

                return [
                    'payment' => $payment->fresh(),
                    'failure' => null,
                    'requires_action' => true,
                    'client_secret' => $result->clientSecret,
                ];
            }

            $paidAt = now();

            $payment->update([
                'status' => PaymentStatus::PAID->value,
                'gateway_status' => $result->status,
                'requires_3ds' => false,
                'failure_reason' => null,
                'taken_by' => $takenBy?->id,
                'paid_at' => $paidAt,
            ]);

            $this->syncOrderPaymentState($payment);

            return [
                'payment' => $payment->fresh(),
                'failure' => null,
                'requires_action' => false,
                'client_secret' => null,
            ];
        });
    }

    private function validatePaymentAmount(Payment $payment): void
    {
        $order = $payment->order;

        $totalMinor = $order->total->amount();
        $paymentMinor = $payment->amount->amount();

        $alreadyPaidMinor = $order->payments()
            ->where('status', PaymentStatus::PAID->value)
            ->get()
            ->sum(
                fn (Payment $paidPayment): int => $paidPayment
                    ->amount
                    ->amount(),
            );

        $remainingMinor = $totalMinor - $alreadyPaidMinor;

        if ($paymentMinor <= 0) {
            throw ValidationException::withMessages([
                'payment' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($remainingMinor <= 0) {
            throw ValidationException::withMessages([
                'payment' => 'This order has already been fully paid.',
            ]);
        }

        if ($paymentMinor > $remainingMinor) {
            throw ValidationException::withMessages([
                'payment' => 'Payment amount exceeds the remaining balance.',
            ]);
        }
    }

    private function syncOrderPaymentState(Payment $payment): void
    {
        $order = $payment->order;

        $totalMinor = $order->total->amount();

        $paidMinor = $order->payments()
            ->where('status', PaymentStatus::PAID->value)
            ->get()
            ->sum(
                fn (Payment $paidPayment): int => $paidPayment
                    ->amount
                    ->amount(),
            );

        if ($paidMinor > $totalMinor) {
            throw ValidationException::withMessages([
                'payment' => 'Payments exceed the order total.',
            ]);
        }

        $isFullyPaid = $paidMinor >= $totalMinor;

        $order->update([
            'is_paid' => $isFullyPaid,
            'paid_at' => $isFullyPaid
                ? ($order->paid_at ?? now())
                : null,
        ]);
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
