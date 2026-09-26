<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\ValueObjects\Failure;
use App\Payments\ValueObjects\Intent;
use App\Payments\ValueObjects\Result;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final class FakeGateway implements PaymentGateway
{
    public const SUCCESS_CARD = '4242424242424242';

    public const THREE_DS_CARD = '4000002500003155';

    public const DECLINE_CARD = '4000000000000002';

    public function __construct(
        private readonly int $latencyMs = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function createIntent(
        Order $order,
        Money $amount,
        array $meta = [],
    ): Intent {
        $this->sleep();

        $cardNumber = $this->normalizeCardNumber(
            (string) ($meta['card_number'] ?? ''),
        );

        $scenario = $this->scenarioFor($cardNumber);
        $intentId = $this->makeIntentId($scenario);

        if ($scenario === 'decline') {
            $this->storeStatus($intentId, 'failed');

            return new Intent(
                id: $intentId,
                status: 'failed',
                failure: new Failure(
                    code: 'card_declined',
                    field: 'card_number',
                    message: 'Your card was declined.',
                ),
            );
        }

        if ($scenario === '3ds') {
            $this->storeStatus($intentId, 'requires_action');

            return new Intent(
                id: $intentId,
                status: 'requires_action',
                requiresAction: true,
                clientSecret: 'fake_secret_'.$intentId,
            );
        }

        $this->storeStatus($intentId, 'succeeded');

        return new Intent(
            id: $intentId,
            status: 'succeeded',
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function confirm(
        string $intentId,
        array $payload = [],
    ): Result {
        $this->sleep();

        $scenario = $this->scenarioFromIntentId($intentId);

        if ($scenario === null) {
            return $this->unknownIntent($intentId);
        }

        $currentStatus = $this->storedStatus($intentId);

        if ($currentStatus === 'succeeded') {
            return new Result(
                id: $intentId,
                status: 'succeeded',
            );
        }

        if ($scenario === 'decline') {
            $this->storeStatus($intentId, 'failed');

            return new Result(
                id: $intentId,
                status: 'failed',
                failure: new Failure(
                    code: 'card_declined',
                    field: 'card_number',
                    message: 'Your card was declined.',
                ),
            );
        }

        if ($scenario === '3ds') {
            $challengeCompleted = (bool) (
                $payload['three_ds_complete']
                ?? $payload['3ds_complete']
                ?? false
            );

            if (! $challengeCompleted) {
                $this->storeStatus($intentId, 'requires_action');

                return new Result(
                    id: $intentId,
                    status: 'requires_action',
                    requiresAction: true,
                    clientSecret: 'fake_secret_'.$intentId,
                );
            }

            $this->storeStatus($intentId, 'succeeded');

            return new Result(
                id: $intentId,
                status: 'succeeded',
            );
        }

        $this->storeStatus($intentId, 'succeeded');

        return new Result(
            id: $intentId,
            status: 'succeeded',
        );
    }

    public function capture(
        string $intentId,
        Money $amount,
    ): Result {
        $this->sleep();

        $scenario = $this->scenarioFromIntentId($intentId);

        if ($scenario === null) {
            return $this->unknownIntent($intentId);
        }

        if ($amount->amount() <= 0) {
            return new Result(
                id: $intentId,
                status: 'failed',
                failure: new Failure(
                    code: 'invalid_amount',
                    field: 'amount',
                    message: 'The capture amount must be greater than zero.',
                ),
            );
        }

        $currentStatus = $this->storedStatus($intentId);

        if ($currentStatus === 'succeeded') {
            return new Result(
                id: $intentId,
                status: 'succeeded',
            );
        }

        if ($scenario === 'decline') {
            return new Result(
                id: $intentId,
                status: 'failed',
                failure: new Failure(
                    code: 'card_declined',
                    field: 'card_number',
                    message: 'Your card was declined.',
                ),
            );
        }

        if ($scenario === '3ds') {
            return new Result(
                id: $intentId,
                status: 'requires_action',
                requiresAction: true,
                clientSecret: 'fake_secret_'.$intentId,
            );
        }

        return new Result(
            id: $intentId,
            status: 'succeeded',
        );
    }

    public function refund(
        Payment $payment,
        Money $amount,
    ): Result {
        $this->sleep();

        if ($amount->amount() <= 0) {
            return new Result(
                id: $payment->gateway_intent_id
                    ?? 'fake_refund_'.$payment->getKey(),
                status: 'failed',
                failure: new Failure(
                    code: 'invalid_amount',
                    field: 'amount',
                    message: 'The refund amount must be greater than zero.',
                ),
            );
        }

        $paymentAmount = $payment->amount;

        if (
            ! $paymentAmount instanceof Money
            || $paymentAmount->currency() !== $amount->currency()
        ) {
            return new Result(
                id: $payment->gateway_intent_id
                    ?? 'fake_refund_'.$payment->getKey(),
                status: 'failed',
                failure: new Failure(
                    code: 'invalid_currency',
                    field: 'amount',
                    message: 'The refund currency does not match the payment.',
                ),
            );
        }

        $alreadyRefunded = $payment->refunded_amount;

        $alreadyRefundedMinor = $alreadyRefunded instanceof Money
            ? $alreadyRefunded->amount()
            : 0;

        $remaining = $paymentAmount->amount() - $alreadyRefundedMinor;

        if ($amount->amount() > $remaining) {
            return new Result(
                id: $payment->gateway_intent_id
                    ?? 'fake_refund_'.$payment->getKey(),
                status: 'failed',
                failure: new Failure(
                    code: 'refund_exceeds_payment',
                    field: 'amount',
                    message: 'The refund amount exceeds the remaining settled amount.',
                ),
            );
        }

        return new Result(
            id: $payment->gateway_intent_id
                ?? 'fake_refund_'.$payment->getKey(),
            status: 'succeeded',
        );
    }

    public function status(string $intentId): Result
    {
        $this->sleep();

        $scenario = $this->scenarioFromIntentId($intentId);

        if ($scenario === null) {
            return $this->unknownIntent($intentId);
        }

        $storedStatus = $this->storedStatus($intentId);

        if ($storedStatus === 'succeeded') {
            return new Result(
                id: $intentId,
                status: 'succeeded',
            );
        }

        if ($storedStatus === 'failed' || $scenario === 'decline') {
            return new Result(
                id: $intentId,
                status: 'failed',
                failure: new Failure(
                    code: 'card_declined',
                    field: 'card_number',
                    message: 'Your card was declined.',
                ),
            );
        }

        if ($storedStatus === 'requires_action' || $scenario === '3ds') {
            return new Result(
                id: $intentId,
                status: 'requires_action',
                requiresAction: true,
                clientSecret: 'fake_secret_'.$intentId,
            );
        }

        return new Result(
            id: $intentId,
            status: 'succeeded',
        );
    }

    private function normalizeCardNumber(string $cardNumber): string
    {
        return preg_replace('/\D+/', '', $cardNumber) ?? '';
    }

    private function scenarioFor(string $cardNumber): string
    {
        return match ($cardNumber) {
            self::THREE_DS_CARD => '3ds',
            self::DECLINE_CARD => 'decline',
            default => 'success',
        };
    }

    private function makeIntentId(string $scenario): string
    {
        return sprintf(
            'fake_%s_%s',
            $scenario,
            Str::lower(Str::random(24)),
        );
    }

    private function scenarioFromIntentId(string $intentId): ?string
    {
        if (str_starts_with($intentId, 'fake_success_')) {
            return 'success';
        }

        if (str_starts_with($intentId, 'fake_3ds_')) {
            return '3ds';
        }

        if (str_starts_with($intentId, 'fake_decline_')) {
            return 'decline';
        }

        return null;
    }

    private function storeStatus(
        string $intentId,
        string $status,
    ): void {
        Cache::put(
            $this->statusCacheKey($intentId),
            $status,
            now()->addHour(),
        );
    }

    private function storedStatus(string $intentId): ?string
    {
        $status = Cache::get(
            $this->statusCacheKey($intentId),
        );

        return is_string($status)
            ? $status
            : null;
    }

    private function statusCacheKey(string $intentId): string
    {
        return 'qresto:payments:fake:intent:'.$intentId;
    }

    private function unknownIntent(string $intentId): Result
    {
        return new Result(
            id: $intentId,
            status: 'failed',
            failure: new Failure(
                code: 'intent_not_found',
                field: null,
                message: 'The payment intent could not be found.',
            ),
        );
    }

    private function sleep(): void
    {
        if ($this->latencyMs <= 0) {
            return;
        }

        usleep($this->latencyMs * 1000);
    }
}
