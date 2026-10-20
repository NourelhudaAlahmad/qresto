<?php

use App\Models\Order;
use App\Payments\Gateways\FakeGateway;
use App\Payments\PaymentManager;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a successful intent for the 4242 test card', function (): void {
    $order = Order::factory()->create();

    $gateway = new FakeGateway;

    $intent = $gateway->createIntent(
        order: $order,
        amount: Money::fromMinor(2500, 'TRY'),
        meta: [
            'card_number' => FakeGateway::SUCCESS_CARD,
        ],
    );

    expect($intent->id)
        ->toStartWith('fake_success_')
        ->and($intent->status)->toBe('succeeded')
        ->and($intent->requiresAction)->toBeFalse()
        ->and($intent->clientSecret)->toBeNull()
        ->and($intent->failure)->toBeNull()
        ->and($intent->succeeded())->toBeTrue()
        ->and($intent->failed())->toBeFalse();
});

it('returns requires action for the 3ds test card', function (): void {
    $order = Order::factory()->create();

    $gateway = new FakeGateway;

    $intent = $gateway->createIntent(
        order: $order,
        amount: Money::fromMinor(2500, 'TRY'),
        meta: [
            'card_number' => FakeGateway::THREE_DS_CARD,
        ],
    );

    expect($intent->id)
        ->toStartWith('fake_3ds_')
        ->and($intent->status)->toBe('requires_action')
        ->and($intent->requiresAction)->toBeTrue()
        ->and($intent->clientSecret)->not->toBeNull()
        ->and($intent->failure)->toBeNull()
        ->and($intent->succeeded())->toBeFalse();
});

it('resolves a 3ds challenge on the second call', function (): void {
    $order = Order::factory()->create();

    $gateway = new FakeGateway;

    $intent = $gateway->createIntent(
        order: $order,
        amount: Money::fromMinor(2500, 'TRY'),
        meta: [
            'card_number' => FakeGateway::THREE_DS_CARD,
        ],
    );

    $beforeChallenge = $gateway->confirm(
        intentId: $intent->id,
    );

    expect($beforeChallenge->status)
        ->toBe('requires_action')
        ->and($beforeChallenge->requiresAction)->toBeTrue()
        ->and($beforeChallenge->clientSecret)->not->toBeNull()
        ->and($beforeChallenge->succeeded())->toBeFalse();

    $confirmed = $gateway->confirm(
        intentId: $intent->id,
        payload: [
            'three_ds_complete' => true,
        ],
    );

    expect($confirmed->id)
        ->toBe($intent->id)
        ->and($confirmed->status)->toBe('succeeded')
        ->and($confirmed->requiresAction)->toBeFalse()
        ->and($confirmed->failure)->toBeNull()
        ->and($confirmed->succeeded())->toBeTrue();
});

it('returns a structured field failure for the decline card', function (): void {
    $order = Order::factory()->create();

    $gateway = new FakeGateway;

    $intent = $gateway->createIntent(
        order: $order,
        amount: Money::fromMinor(2500, 'TRY'),
        meta: [
            'card_number' => FakeGateway::DECLINE_CARD,
        ],
    );

    expect($intent->status)
        ->toBe('failed')
        ->and($intent->requiresAction)->toBeFalse()
        ->and($intent->succeeded())->toBeFalse()
        ->and($intent->failed())->toBeTrue()
        ->and($intent->failure)->not->toBeNull()
        ->and($intent->failure?->code)->toBe('card_declined')
        ->and($intent->failure?->field)->toBe('card_number')
        ->and($intent->failure?->message)->toBe('Your card was declined.');
});

it('returns a structured failure for an unknown intent', function (): void {
    $gateway = new FakeGateway;

    $result = $gateway->status('unknown-intent');

    expect($result->status)
        ->toBe('failed')
        ->and($result->failed())->toBeTrue()
        ->and($result->failure)->not->toBeNull()
        ->and($result->failure?->code)->toBe('intent_not_found')
        ->and($result->failure?->field)->toBeNull();
});

it('resolves the configured fake driver through the payment manager', function (): void {
    config()->set('qresto.payments.driver', 'fake');
    config()->set('qresto.payments.fake.latency_ms', 0);

    $gateway = app(PaymentManager::class)->driver();

    expect($gateway)->toBeInstanceOf(FakeGateway::class);
});
it('reports a 3ds intent as succeeded after the challenge is completed', function (): void {
    $order = Order::factory()->create();

    $gateway = app(PaymentManager::class)->driver();

    $intent = $gateway->createIntent(
        order: $order,
        amount: Money::fromMinor(2500, 'TRY'),
        meta: [
            'card_number' => FakeGateway::THREE_DS_CARD,
        ],
    );

    expect($gateway->status($intent->id)->status)
        ->toBe('requires_action');

    $confirmed = $gateway->confirm(
        intentId: $intent->id,
        payload: [
            'three_ds_complete' => true,
        ],
    );

    expect($confirmed->status)->toBe('succeeded')
        ->and($confirmed->requiresAction)->toBeFalse();

    $status = $gateway->status($intent->id);

    expect($status->status)->toBe('succeeded')
        ->and($status->requiresAction)->toBeFalse()
        ->and($status->failure)->toBeNull();
});
