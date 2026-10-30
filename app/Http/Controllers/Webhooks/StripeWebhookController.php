<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\SettlePayment;
use App\Enums\Capability;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\PaymentDisputed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use UnexpectedValueException;

/**
 * POST /webhooks/stripe (#35)
 *
 * Signature-verified, idempotent by event id: the event is recorded and
 * acted on in one transaction, so a replay is a no-op and a failure lets
 * Stripe retry. Settles payments that finished outside the browser,
 * records failures, refunds and disputes, and writes order events.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, SettlePayment $settle): JsonResponse
    {
        $secret = (string) config('qresto.payments.stripe.webhook_secret');

        if ($secret === '') {
            return response()->json(['message' => 'Stripe webhooks are not configured.'], 503);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $secret,
                (int) config('qresto.payments.stripe.webhook_tolerance', 300),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $handled = DB::transaction(function () use ($event, $settle): bool {
            $recorded = DB::table('webhook_events')->insertOrIgnore([
                'provider' => 'stripe',
                'event_id' => $event->id,
                'type' => $event->type,
                'received_at' => now(),
            ]);

            if ($recorded === 0) {
                return false; // already processed: a replay or a retry
            }

            $this->handle($event, $settle);

            return true;
        });

        return response()->json(['received' => true, 'duplicate' => ! $handled]);
    }

    private function handle(Event $event, SettlePayment $settle): void
    {
        /** @var array<string, mixed> $object */
        $object = $event->data->object->toArray();

        match ($event->type) {
            'payment_intent.succeeded' => $this->succeeded($object, $settle),
            'payment_intent.payment_failed' => $this->failed($object),
            'charge.refunded' => $this->refunded($object),
            'charge.dispute.created' => $this->disputed($object),
            default => null, // not ours to act on
        };
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function succeeded(array $intent, SettlePayment $settle): void
    {
        $payment = $this->paymentFor((string) ($intent['id'] ?? ''));

        if ($payment === null || $payment->status === PaymentStatus::PAID->value) {
            return;
        }

        // The signed intent goes to the gateway, so no second API call.
        try {
            $result = $settle->handle($payment, null, [
                'intent' => PaymentIntent::constructFrom($intent),
            ]);
        } catch (ValidationException $exception) {
            // A webhook must not answer with a redirect, or Stripe retries
            // for days. Record it and leave it for a person.
            Log::error('Stripe webhook could not settle a payment', [
                'payment' => $payment->id,
                'intent' => $intent['id'] ?? null,
                'errors' => $exception->errors(),
            ]);

            return;
        }

        if ($result['payment']->status === PaymentStatus::PAID->value) {
            $this->event($payment->order, 'payment_settled', ['intent' => $intent['id'] ?? null]);
        }
    }

    /**
     * @param  array<string, mixed>  $intent
     */
    private function failed(array $intent): void
    {
        $payment = $this->paymentFor((string) ($intent['id'] ?? ''));

        if ($payment === null || $payment->status === PaymentStatus::PAID->value) {
            return;
        }

        /** @var array<string, mixed> $error */
        $error = is_array($intent['last_payment_error'] ?? null) ? $intent['last_payment_error'] : [];

        $payment->update([
            'status' => PaymentStatus::FAILED->value,
            'gateway_status' => (string) ($intent['status'] ?? 'failed'),
            'requires_3ds' => false,
            'failure_reason' => (string) ($error['message'] ?? 'The payment failed.'),
        ]);

        $this->event($payment->order, 'payment_failed', ['intent' => $intent['id'] ?? null]);
    }

    /**
     * @param  array<string, mixed>  $charge
     */
    private function refunded(array $charge): void
    {
        $payment = $this->paymentFor((string) ($charge['payment_intent'] ?? ''));

        if ($payment === null) {
            return;
        }

        $refunded = (int) ($charge['amount_refunded'] ?? 0);
        $already = $payment->refunded_amount?->amount() ?? 0;

        // Our own refunds already updated the payment; only move forward.
        if ($refunded <= $already) {
            return;
        }

        $payment->update([
            'refunded_amount' => $refunded,
            'refunded_at' => now(),
            'status' => $refunded >= $payment->amount->amount()
                ? PaymentStatus::REFUNDED->value
                : $payment->status,
        ]);

        $this->event($payment->order, 'payment_refunded', [
            'charge' => $charge['id'] ?? null,
            'amount_refunded' => $refunded,
        ]);
    }

    /**
     * @param  array<string, mixed>  $dispute
     */
    private function disputed(array $dispute): void
    {
        $payment = $this->paymentFor((string) ($dispute['payment_intent'] ?? ''));

        if ($payment === null) {
            return;
        }

        $order = $payment->order;
        $amount = (int) ($dispute['amount'] ?? 0);
        $reason = (string) ($dispute['reason'] ?? 'unknown');

        $this->event($order, 'payment_disputed', [
            'dispute' => $dispute['id'] ?? null,
            'reason' => $reason,
            'amount' => $amount,
        ]);

        $managers = User::query()
            ->where('restaurant_id', $order->restaurant_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->can(Capability::FINANCIAL_REPORTS->value));

        Notification::send($managers, new PaymentDisputed($order, $amount, $reason));
    }

    private function paymentFor(string $intentId): ?Payment
    {
        $payment = Payment::query()
            ->where('gateway', 'stripe')
            ->where('gateway_intent_id', $intentId)
            ->with('order')
            ->first();

        if ($payment === null) {
            Log::info('Stripe webhook for an unknown payment intent', ['intent' => $intentId]);
        }

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function event(Order $order, string $kind, array $meta): void
    {
        OrderEvent::query()->create([
            'order_id' => $order->id,
            'from_status' => $order->status->value,
            'to_status' => $order->status->value,
            'actor_id' => null,
            'actor_kind' => 'system',
            'occurred_at' => now(),
            'meta' => ['kind' => $kind, 'source' => 'stripe_webhook', ...$meta],
        ]);
    }
}
