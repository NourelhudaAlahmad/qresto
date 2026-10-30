<?php

use App\Models\Order;
use App\Models\Payment;
use App\Payments\Gateways\FakeGateway;
use App\Payments\Gateways\StripeGateway;
use App\Payments\PaymentManager;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Stripe\PaymentIntent;
use Tests\Support\FakeStripe;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->stripe = FakeStripe::install();
    $this->gateway = app(StripeGateway::class);
    $this->order = Order::factory()->create(['code' => '#A-2001']);
    $this->amount = Money::fromMinor(2000, 'USD');
});

afterEach(fn () => FakeStripe::uninstall());

it('runs the suite on the fake driver unless told otherwise', function () {
    expect(config('qresto.payments.driver'))->toBe('fake')
        ->and(app(PaymentManager::class)->driver())->toBeInstanceOf(FakeGateway::class)
        ->and(app(PaymentManager::class)->driver('stripe'))->toBeInstanceOf(StripeGateway::class);
});

it('creates and confirms an intent from a tokenised card, with no redirects', function () {
    $this->stripe->respond(FakeStripe::intent('pi_ok', 'succeeded'));

    $intent = $this->gateway->createIntent($this->order, $this->amount, [
        'payment_method' => 'pm_card_visa',
        'idempotency_key' => 'k-123',
    ]);

    expect($intent->succeeded())->toBeTrue()
        ->and($intent->id)->toBe('pi_ok');

    $request = $this->stripe->requests[0];

    expect($request['method'])->toBe('post')
        ->and($request['url'])->toEndWith('/v1/payment_intents')
        ->and($request['params'])->toMatchArray([
            'amount' => 2000,
            'currency' => 'usd',
            'payment_method' => 'pm_card_visa',
            'confirm' => true,
            'automatic_payment_methods' => ['enabled' => true, 'allow_redirects' => 'never'],
        ])
        ->and($request['params']['metadata']['order_code'])->toBe('#A-2001')
        ->and(implode("\n", $request['headers']))->toContain('Idempotency-Key: qresto-intent-k-123')
        // No card number ever travels through QResto.
        ->and(json_encode($request['params']))->not->toContain('card_number');
});

it('hands back the client secret when the bank wants 3-D Secure', function () {
    $this->stripe->respond(FakeStripe::intent('pi_3ds', 'requires_action'));

    $intent = $this->gateway->createIntent($this->order, $this->amount, ['payment_method' => 'pm_card_threeDSecure2Required']);

    expect($intent->requiresAction)->toBeTrue()
        ->and($intent->clientSecret)->toBe('pi_3ds_secret_abc')
        ->and($intent->failed())->toBeFalse();
});

it('maps a decline onto the same structured failure as the fake driver', function () {
    $this->stripe->decline('card_declined', 'generic_decline');

    $stripe = $this->gateway->createIntent($this->order, $this->amount, ['payment_method' => 'pm_card_chargeDeclined']);

    $fake = (new FakeGateway)->createIntent($this->order, $this->amount, [
        'card_number' => FakeGateway::DECLINE_CARD,
        'card_expiry' => '12/30',
        'card_cvc' => '123',
    ]);

    expect($stripe->failed())->toBeTrue()
        ->and($stripe->failure->toArray())->toBe($fake->failure->toArray());
});

it('points other declines at the right field', function (string $code, ?string $decline, string $field, string $message) {
    $this->stripe->decline($code, $decline);

    $failure = $this->gateway->createIntent($this->order, $this->amount, ['payment_method' => 'pm_x'])->failure;

    expect($failure->field)->toBe($field)
        ->and($failure->message)->toBe($message);
})->with([
    'insufficient funds' => ['card_declined', 'insufficient_funds', 'card_number', 'Your card has insufficient funds.'],
    'wrong cvc' => ['incorrect_cvc', null, 'card_cvc', 'Your card’s security code is incorrect.'],
    'expired' => ['expired_card', null, 'card_expiry', 'Your card has expired.'],
    'wrong number' => ['incorrect_number', null, 'card_number', 'Your card number is incorrect.'],
]);

it('treats a failed 3-D Secure as a structured failure', function () {
    $this->stripe->respond(FakeStripe::intent('pi_3ds', 'requires_payment_method', [
        'last_payment_error' => ['type' => 'card_error', 'code' => 'payment_intent_authentication_failure'],
    ]));

    $result = $this->gateway->confirm('pi_3ds');

    expect($result->failed())->toBeTrue()
        ->and($result->failure->code)->toBe('authentication_failed');
});

it('confirms after in-page 3-D Secure by reading the intent', function () {
    $this->stripe->respond(FakeStripe::intent('pi_3ds', 'succeeded'));

    expect($this->gateway->confirm('pi_3ds')->succeeded())->toBeTrue()
        ->and($this->stripe->requests[0]['method'])->toBe('get');
});

it('settles from a signed webhook intent without calling Stripe again', function () {
    $intent = PaymentIntent::constructFrom(FakeStripe::intent('pi_hook', 'succeeded'));

    expect($this->gateway->confirm('pi_hook', ['intent' => $intent])->succeeded())->toBeTrue()
        ->and($this->stripe->requests)->toBe([]);
});

it('keeps a processing payment unsettled', function () {
    $this->stripe->respond(FakeStripe::intent('pi_slow', 'processing'));

    $intent = $this->gateway->createIntent($this->order, $this->amount, ['payment_method' => 'pm_x']);

    expect($intent->succeeded())->toBeFalse()
        ->and($intent->failed())->toBeFalse()
        ->and($intent->status)->toBe('processing');
});

it('captures and refunds', function () {
    $this->stripe->respond(FakeStripe::intent('pi_cap', 'succeeded'));

    expect($this->gateway->capture('pi_cap', $this->amount)->succeeded())->toBeTrue()
        ->and($this->stripe->requests[0]['url'])->toEndWith('/v1/payment_intents/pi_cap/capture');

    $payment = Payment::factory()->forOrder($this->order)->create([
        'gateway' => 'stripe',
        'gateway_intent_id' => 'pi_cap',
        'amount' => 2000,
    ]);

    $this->stripe->respond(['id' => 're_1', 'object' => 'refund', 'status' => 'succeeded', 'amount' => 500]);

    expect($this->gateway->refund($payment, Money::fromMinor(500, 'USD'))->succeeded())->toBeTrue()
        ->and($this->stripe->requests[1]['params'])->toMatchArray(['payment_intent' => 'pi_cap', 'amount' => 500]);
});

it('reports an unreachable Stripe as a failure, not a crash', function () {
    $this->stripe->respond(['error' => ['type' => 'api_error', 'message' => 'boom']], 500);

    $intent = $this->gateway->createIntent($this->order, $this->amount, ['payment_method' => 'pm_x']);

    expect($intent->failure->code)->toBe('gateway_unavailable');
});

it('answers the system-health probe', function () {
    $this->stripe->respond(['object' => 'balance', 'available' => [], 'pending' => []]);

    expect($this->gateway->probe()['status'])->toBe('ok');

    $this->stripe->respond(['error' => ['type' => 'api_error', 'message' => 'down']], 500);

    expect($this->gateway->probe()['status'])->toBe('failing');
});
