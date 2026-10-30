<?php

namespace App\Payments\Gateways;

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Contracts\PaymentGateway;
use App\Payments\Contracts\ProbesHealth;
use App\Payments\ValueObjects\Failure;
use App\Payments\ValueObjects\Intent;
use App\Payments\ValueObjects\Result;
use App\Support\Money;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Throwable;

/**
 * Stripe behind the same PaymentGateway contract as FakeGateway (#35).
 *
 * Card details never reach QResto: the browser turns them into a
 * PaymentMethod with Stripe Elements and checkout sends only its id. Intents
 * use automatic payment methods with redirects disabled, so 3-D Secure is
 * always handled in-page (requires_action + client secret), never a redirect.
 */
final class StripeGateway implements PaymentGateway, ProbesHealth
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {}

    /**
     * @param  array<string, mixed>  $meta  payment_method (pm_…), idempotency_key
     */
    public function createIntent(Order $order, Money $amount, array $meta = []): Intent
    {
        $params = [
            'amount' => $amount->amount(),
            'currency' => strtolower($amount->currency()),
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
            'description' => "QResto {$order->code}",
            'metadata' => [
                'order_id' => (string) $order->id,
                'order_code' => (string) $order->code,
                'restaurant_id' => (string) $order->restaurant_id,
            ],
        ];

        if (is_string($meta['payment_method'] ?? null) && $meta['payment_method'] !== '') {
            $params['payment_method'] = $meta['payment_method'];
            $params['confirm'] = true;
        }

        $options = is_string($meta['idempotency_key'] ?? null)
            ? ['idempotency_key' => 'qresto-intent-'.$meta['idempotency_key']]
            : [];

        try {
            return $this->toIntent($this->stripe->paymentIntents->create($params, $options));
        } catch (CardException $exception) {
            $intent = $exception->getError()?->payment_intent;

            return new Intent(
                id: is_object($intent) && isset($intent->id) ? (string) $intent->id : 'pi_declined',
                status: 'failed',
                failure: $this->failureFrom($exception->getStripeCode(), $exception->getDeclineCode()),
            );
        } catch (ApiErrorException) {
            return new Intent(id: 'pi_unavailable', status: 'failed', failure: $this->unavailable());
        }
    }

    /**
     * After in-page 3-D Secure the intent has usually already succeeded.
     * A webhook may pass the signed intent as $payload['intent'], so
     * settling from a webhook makes no second call to Stripe.
     *
     * @param  array<string, mixed>  $payload
     */
    public function confirm(string $intentId, array $payload = []): Result
    {
        try {
            $intent = $payload['intent'] ?? null;

            if (! $intent instanceof PaymentIntent || $intent->id !== $intentId) {
                $intent = $this->stripe->paymentIntents->retrieve($intentId);
            }

            if ($intent->status === PaymentIntent::STATUS_REQUIRES_CONFIRMATION) {
                $intent = $this->stripe->paymentIntents->confirm($intentId);
            }

            return $this->toResult($intent);
        } catch (CardException $exception) {
            return new Result($intentId, 'failed', failure: $this->failureFrom($exception->getStripeCode(), $exception->getDeclineCode()));
        } catch (ApiErrorException) {
            return new Result($intentId, 'failed', failure: $this->unavailable());
        }
    }

    public function capture(string $intentId, Money $amount): Result
    {
        if ($amount->amount() <= 0) {
            return new Result($intentId, 'failed', failure: new Failure(
                code: 'invalid_amount',
                field: 'amount',
                message: 'The capture amount must be greater than zero.',
            ));
        }

        try {
            return $this->toResult($this->stripe->paymentIntents->capture($intentId, [
                'amount_to_capture' => $amount->amount(),
            ]));
        } catch (ApiErrorException) {
            return new Result($intentId, 'failed', failure: $this->unavailable());
        }
    }

    public function refund(Payment $payment, Money $amount): Result
    {
        if ($amount->amount() <= 0) {
            return new Result((string) $payment->gateway_intent_id, 'failed', failure: new Failure(
                code: 'invalid_amount',
                field: 'amount',
                message: 'The refund amount must be greater than zero.',
            ));
        }

        try {
            $refund = $this->stripe->refunds->create([
                'payment_intent' => (string) $payment->gateway_intent_id,
                'amount' => $amount->amount(),
            ], [
                // One refund per amount step, safe to retry.
                'idempotency_key' => sprintf('qresto-refund-%d-%d-%d', $payment->id, $payment->refunded_amount?->amount() ?? 0, $amount->amount()),
            ]);

            // A pending card refund completes on its own; charge.refunded
            // confirms it through the webhook.
            return match ($refund->status) {
                'succeeded', 'pending' => new Result((string) $refund->id, 'succeeded'),
                default => new Result((string) $refund->id, 'failed', failure: new Failure(
                    code: 'refund_failed',
                    field: 'amount',
                    message: 'The refund could not be completed.',
                )),
            };
        } catch (ApiErrorException $exception) {
            return new Result((string) $payment->gateway_intent_id, 'failed', failure: new Failure(
                code: $exception->getStripeCode() ?? 'refund_failed',
                field: 'amount',
                message: 'The refund could not be completed.',
            ));
        }
    }

    public function status(string $intentId): Result
    {
        try {
            return $this->toResult($this->stripe->paymentIntents->retrieve($intentId));
        } catch (ApiErrorException) {
            return new Result($intentId, 'failed', failure: $this->unavailable());
        }
    }

    /**
     * For system health (#30).
     *
     * @return array{status: 'ok'|'warning'|'failing', detail: string}
     */
    public function probe(): array
    {
        try {
            $this->stripe->balance->retrieve();

            return ['status' => 'ok', 'detail' => 'operational · stripe'];
        } catch (Throwable $exception) {
            return ['status' => 'failing', 'detail' => 'stripe unreachable: '.class_basename($exception)];
        }
    }

    private function toIntent(PaymentIntent $intent): Intent
    {
        $result = $this->toResult($intent);

        return new Intent(
            id: $result->id,
            status: $result->status,
            requiresAction: $result->requiresAction,
            clientSecret: $result->clientSecret,
            failure: $result->failure,
        );
    }

    private function toResult(PaymentIntent $intent): Result
    {
        $error = $intent->last_payment_error ?? null;

        return match ($intent->status) {
            PaymentIntent::STATUS_SUCCEEDED => new Result($intent->id, 'succeeded'),
            PaymentIntent::STATUS_REQUIRES_ACTION => new Result(
                $intent->id,
                'requires_action',
                requiresAction: true,
                clientSecret: $intent->client_secret,
            ),
            PaymentIntent::STATUS_REQUIRES_PAYMENT_METHOD => $error !== null
                // The card was tried and refused (3DS failed, declined…).
                ? new Result($intent->id, 'failed', failure: $this->failureFrom($error->code ?? null, $error->decline_code ?? null))
                : new Result($intent->id, 'requires_payment_method', clientSecret: $intent->client_secret),
            PaymentIntent::STATUS_CANCELED => new Result($intent->id, 'failed', failure: new Failure(
                code: 'payment_canceled',
                field: null,
                message: 'This payment was cancelled. Try again.',
            )),
            // processing, requires_capture, requires_confirmation: not
            // settled, not failed. The webhook finishes the job.
            default => new Result($intent->id, (string) $intent->status),
        };
    }

    /**
     * Stripe's codes onto the structured failure C7 renders inline. A plain
     * decline is exactly what FakeGateway returns for its decline card.
     */
    private function failureFrom(?string $code, ?string $declineCode): Failure
    {
        return match (true) {
            in_array($code, ['incorrect_number', 'invalid_number'], true) => new Failure(
                code: 'incorrect_number',
                field: 'card_number',
                message: 'Your card number is incorrect.',
            ),
            in_array($code, ['expired_card', 'invalid_expiry_month', 'invalid_expiry_year'], true) => new Failure(
                code: 'expired_card',
                field: 'card_expiry',
                message: 'Your card has expired.',
            ),
            in_array($code, ['incorrect_cvc', 'invalid_cvc'], true) => new Failure(
                code: 'incorrect_cvc',
                field: 'card_cvc',
                message: 'Your card’s security code is incorrect.',
            ),
            $code === 'processing_error' => new Failure(
                code: 'processing_error',
                field: null,
                message: 'Your card could not be processed. Try again.',
            ),
            $code === 'payment_intent_authentication_failure' || $code === 'authentication_required' => new Failure(
                code: 'authentication_failed',
                field: 'card_number',
                message: 'Your bank could not verify this payment. Try again or use another card.',
            ),
            $declineCode === 'insufficient_funds' => new Failure(
                code: 'card_declined',
                field: 'card_number',
                message: 'Your card has insufficient funds.',
            ),
            default => new Failure(
                code: 'card_declined',
                field: 'card_number',
                message: 'Your card was declined.',
            ),
        };
    }

    private function unavailable(): Failure
    {
        return new Failure(
            code: 'gateway_unavailable',
            field: null,
            message: 'We could not reach the card network. Try again in a moment.',
        );
    }
}
