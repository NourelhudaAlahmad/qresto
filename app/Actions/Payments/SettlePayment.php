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
            $payment->loadMissing('order');

            if ($payment->status === PaymentStatus::PAID->value) {
                return [
                    'payment' => $payment,
                    'failure' => null,
                    'requires_action' => false,
                    'client_secret' => null,
                ];
            }

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

                $payment->order->update([
                    'is_paid' => true,
                    'paid_at' => $paidAt,
                ]);

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

            $payment->order->update([
                'is_paid' => true,
                'paid_at' => $paidAt,
            ]);

            return [
                'payment' => $payment->fresh(),
                'failure' => null,
                'requires_action' => false,
                'client_secret' => null,
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
