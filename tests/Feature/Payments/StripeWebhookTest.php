<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderEvent;
use App\Models\Payment;
use App\Models\Restaurant;
use App\Models\User;
use App\Notifications\PaymentDisputed;
use Database\Seeders\RolesSeeder;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FakeStripe;

uses(RefreshDatabase::class);

const WEBHOOK_SECRET = 'whsec_test_suite';

beforeEach(function () {
    $this->seed(RolesSeeder::class);

    // Any call to Stripe from a webhook would throw: none is expected.
    $this->stripe = FakeStripe::install();

    $this->restaurant = Restaurant::factory()->create(['currency' => 'USD']);
    $this->order = Order::factory()->for($this->restaurant)->create([
        'status' => 'pending',
        'is_paid' => false,
        'currency' => 'USD',
        'subtotal' => 2000,
        'service_amount' => 0,
        'tip_amount' => 0,
        'discount_amount' => 0,
        'total' => 2000,
    ]);
    $this->payment = Payment::factory()->forOrder($this->order)->create([
        'method' => 'card',
        'gateway' => 'stripe',
        'gateway_intent_id' => 'pi_live',
        'status' => PaymentStatus::REQUIRES_ACTION->value,
        'amount' => 2000,
        'refunded_amount' => 0,
    ]);
});

afterEach(fn () => FakeStripe::uninstall());

/**
 * @param  array<string, mixed>  $object
 */
function stripeEvent(string $id, string $type, array $object): string
{
    return json_encode([
        'id' => $id,
        'object' => 'event',
        'type' => $type,
        'created' => time(),
        'data' => ['object' => $object],
    ], JSON_THROW_ON_ERROR);
}

function deliver(string $payload, ?string $signature = null)
{
    return test()->call('POST', '/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $signature ?? FakeStripe::sign($payload, WEBHOOK_SECRET),
    ], $payload);
}

it('rejects a forged payload and changes nothing', function () {
    $payload = stripeEvent('evt_forged', 'payment_intent.succeeded', FakeStripe::intent('pi_live', 'succeeded'));

    deliver($payload, FakeStripe::sign($payload, 'whsec_someone_else'))->assertStatus(400);
    deliver($payload, 'garbage')->assertStatus(400);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::REQUIRES_ACTION->value)
        ->and(DB::table('webhook_events')->count())->toBe(0);
});

it('rejects a correctly signed but stale payload (replay window)', function () {
    $payload = stripeEvent('evt_old', 'payment_intent.succeeded', FakeStripe::intent('pi_live', 'succeeded'));

    deliver($payload, FakeStripe::sign($payload, WEBHOOK_SECRET, time() - 3600))->assertStatus(400);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::REQUIRES_ACTION->value);
});

it('settles a payment that succeeded, and writes an order event', function () {
    deliver(stripeEvent('evt_1', 'payment_intent.succeeded', FakeStripe::intent('pi_live', 'succeeded')))
        ->assertOk()
        ->assertJson(['received' => true, 'duplicate' => false]);

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::PAID->value)
        ->and($this->order->fresh()->is_paid)->toBeTrue()
        ->and(OrderEvent::query()->where('order_id', $this->order->id)->get()->pluck('meta.kind')->all())->toContain('payment_settled')
        ->and($this->stripe->requests)->toBe([]);
});

it('treats a replayed event as a no-op', function () {
    $payload = stripeEvent('evt_same', 'payment_intent.succeeded', FakeStripe::intent('pi_live', 'succeeded'));

    deliver($payload)->assertOk();
    $events = OrderEvent::query()->where('order_id', $this->order->id)->count();

    deliver($payload)->assertOk()->assertJson(['duplicate' => true]);
    deliver($payload)->assertOk()->assertJson(['duplicate' => true]);

    expect(OrderEvent::query()->where('order_id', $this->order->id)->count())->toBe($events)
        ->and(DB::table('webhook_events')->where('event_id', 'evt_same')->count())->toBe(1)
        ->and(Payment::query()->where('order_id', $this->order->id)->count())->toBe(1);
});

it('records a failed payment', function () {
    deliver(stripeEvent('evt_fail', 'payment_intent.payment_failed', FakeStripe::intent('pi_live', 'requires_payment_method', [
        'last_payment_error' => ['code' => 'card_declined', 'message' => 'Your card was declined.'],
    ])))->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::FAILED->value)
        ->and($payment->failure_reason)->toBe('Your card was declined.');
});

it('never un-pays a settled payment on a late failure event', function () {
    $this->payment->update(['status' => PaymentStatus::PAID->value]);

    deliver(stripeEvent('evt_late', 'payment_intent.payment_failed', FakeStripe::intent('pi_live', 'requires_payment_method')))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::PAID->value);
});

it('applies a refund made in the Stripe dashboard', function () {
    $this->payment->update(['status' => PaymentStatus::PAID->value]);

    deliver(stripeEvent('evt_refund', 'charge.refunded', [
        'id' => 'ch_1',
        'object' => 'charge',
        'payment_intent' => 'pi_live',
        'amount_refunded' => $this->payment->amount->amount(),
    ]))->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::REFUNDED->value)
        ->and($payment->refunded_amount->amount())->toBe($this->payment->amount->amount());
});

it('records a dispute and tells the people who see the money', function () {
    Notification::fake();

    $sara = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'is_active' => true]);
    $sara->assignRole('manager');
    $nadia = User::factory()->create(['restaurant_id' => $this->restaurant->id, 'is_active' => true]);
    $nadia->assignRole('waiter');

    deliver(stripeEvent('evt_dispute', 'charge.dispute.created', [
        'id' => 'dp_1',
        'object' => 'dispute',
        'payment_intent' => 'pi_live',
        'amount' => 2000,
        'reason' => 'fraudulent',
    ]))->assertOk();

    expect(OrderEvent::query()->where('order_id', $this->order->id)->get()->pluck('meta.kind')->all())->toContain('payment_disputed');

    Notification::assertSentTo($sara, PaymentDisputed::class);
    Notification::assertNotSentTo($nadia, PaymentDisputed::class);
});

it('answers 200 and logs when a payment cannot be settled, so Stripe does not retry forever', function () {
    $this->payment->update(['amount' => 0]);

    deliver(stripeEvent('evt_bad', 'payment_intent.succeeded', FakeStripe::intent('pi_live', 'succeeded')))
        ->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::REQUIRES_ACTION->value)
        ->and(DB::table('webhook_events')->where('event_id', 'evt_bad')->exists())->toBeTrue();
});

it('ignores intents that are not ours', function () {
    deliver(stripeEvent('evt_other', 'payment_intent.succeeded', FakeStripe::intent('pi_someone_else', 'succeeded')))->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::REQUIRES_ACTION->value);
});

it('refuses to run without a webhook secret', function () {
    config(['qresto.payments.stripe.webhook_secret' => '']);

    deliver(stripeEvent('evt_x', 'payment_intent.succeeded', FakeStripe::intent('pi_live', 'succeeded')))->assertStatus(503);
});

it('is reachable without a CSRF token, with the CSRF guard really on', function () {
    // The guard skips itself in tests; switch that off so this request goes
    // through it exactly as Stripe's would (a 419 before the fix).
    $this->app->bind(
        PreventRequestForgery::class,
        fn ($app) => new class($app, $app->make(Encrypter::class)) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        },
    );

    deliver(stripeEvent('evt_csrf', 'payment_intent.succeeded', FakeStripe::intent('pi_live', 'succeeded')))
        ->assertOk();

    // …while an ordinary form post still needs its token.
    $this->post('/checkout/promo', ['promo_code' => 'X'])->assertStatus(419);
});
