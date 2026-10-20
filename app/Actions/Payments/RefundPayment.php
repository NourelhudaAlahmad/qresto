<?php

namespace App\Actions\Payments;

use App\Enums\Capability;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Payments\PaymentManager;
use App\Payments\ValueObjects\Failure;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RefundPayment
{
    public function __construct(
        private readonly PaymentManager $payments,
    ) {}

    /**
     * @return array{
     *     payment: Payment,
     *     failure: Failure|null
     * }
     */
    public function handle(
        Payment $payment,
        Money $amount,
        User $actor,
    ): array {
        if (! $actor->can(Capability::TAKE_PAYMENT->value)) {
            throw new AuthorizationException(
                'You are not allowed to refund payments.',
            );
        }

        return DB::transaction(function () use (
            $payment,
            $amount,
        ): array {
            $payment->loadMissing('order');

            if ($payment->status !== PaymentStatus::PAID->value) {
                throw ValidationException::withMessages([
                    'payment' => 'Only paid payments can be refunded.',
                ]);
            }

            if ($amount->currency() !== $payment->amount->currency()) {
                throw ValidationException::withMessages([
                    'amount' => 'The refund currency does not match the payment currency.',
                ]);
            }

            if ($amount->amount() <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The refund amount must be greater than zero.',
                ]);
            }

            $alreadyRefunded = $payment->refunded_amount
                ?? Money::fromMinor(
                    0,
                    $payment->amount->currency(),
                );

            $remaining = $payment->amount->subtract(
                $alreadyRefunded,
            );

            if ($amount->amount() > $remaining->amount()) {
                throw ValidationException::withMessages([
                    'amount' => 'The refund amount exceeds the remaining payment amount.',
                ]);
            }

            if ($payment->gateway !== null) {
                $gateway = $this->payments->driver(
                    $payment->gateway,
                );

                $result = $gateway->refund(
                    payment: $payment,
                    amount: $amount,
                );

                if ($result->failed()) {
                    return [
                        'payment' => $payment->fresh(),
                        'failure' => $result->failure,
                    ];
                }
            }

            $refundedAmount = $alreadyRefunded->add($amount);
            $fullyRefunded = $refundedAmount->amount()
                === $payment->amount->amount();

            $payment->update([
                'refunded_amount' => $refundedAmount,
                'refunded_at' => now(),
                'status' => $fullyRefunded
                    ? PaymentStatus::REFUNDED->value
                    : PaymentStatus::PAID->value,
            ]);

            return [
                'payment' => $payment->fresh(),
                'failure' => null,
            ];
        });
    }
}
