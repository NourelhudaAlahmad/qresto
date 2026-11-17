<?php

use App\Models\Order;
use App\Models\Promotion;
use App\Payments\Gateways\FakeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The 3-D Secure step used to be lost on the redirect back to checkout:
// the page always received 3ds_required = false.
it('shows the verification step after a 3-D Secure card, even after a reload', function () {
    $context = createCheckoutTestContext();
    createCheckoutTestLine(context: $context, qty: 1, unitPrice: 5570);

    $this->withCookie('qresto_table_session', $context['session']->token)
        ->post(route('checkout.store'), [
            'method' => 'card',
            'tip_pct' => 0,
            'split_ways' => 1,
            'idempotency_key' => 'three-ds-roundtrip',
            'card_number' => FakeGateway::THREE_DS_CARD,
            'card_expiry' => '12/30',
            'card_cvc' => '123',
        ])
        ->assertRedirect(route('checkout'));

    $order = Order::query()->where('idempotency_key', 'three-ds-roundtrip')->firstOrFail();

    foreach ([1, 2] as $visit) {
        $this->withCookie('qresto_table_session', $context['session']->token)
            ->get(route('checkout'))
            ->assertInertia(fn ($page) => $page
                ->where('gateway.3ds_required', true)
                ->where('gateway.order_id', $order->id));
    }
});

/**
 * @return array<string, mixed>
 */
function threeDsCheckout(string $key, string $card = FakeGateway::THREE_DS_CARD): array
{
    return [
        'method' => 'card',
        'tip_pct' => 0,
        'split_ways' => 1,
        'idempotency_key' => $key,
        'card_number' => $card,
        'card_expiry' => '12/30',
        'card_cvc' => '123',
    ];
}

it('replaces a checkout left at 3-D Secure instead of placing a second order', function () {
    $context = createCheckoutTestContext();
    createCheckoutTestLine(context: $context, qty: 1, unitPrice: 5570);

    Promotion::query()->create([
        'restaurant_id' => $context['restaurant']->id,
        'code' => 'SAVE10',
        'kind' => 'percent',
        'value' => '10.00',
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'max_uses' => 1,
        'uses' => 0,
    ]);

    $cookie = $context['session']->token;

    // The guest starts with the promo, reaches 3-D Secure and walks away…
    $this->withCookie('qresto_table_session', $cookie)
        ->withSession(['checkout.promo_code' => 'SAVE10'])
        ->post(route('checkout.store'), threeDsCheckout('first-try'))
        ->assertRedirect(route('checkout'));

    $abandoned = Order::query()->where('idempotency_key', 'first-try')->firstOrFail();

    expect($abandoned->promo_code)->toBe('SAVE10')
        ->and(Promotion::query()->value('uses'))->toBe(1);

    // …then pays with another card, using the same (single-use) promo.
    $this->withCookie('qresto_table_session', $cookie)
        ->withSession(['checkout.promo_code' => 'SAVE10'])
        ->post(route('checkout.store'), threeDsCheckout('second-try', FakeGateway::SUCCESS_CARD))
        ->assertSessionHasNoErrors();

    $orders = Order::query()->where('table_session_id', $context['session']->id)->get();

    expect($orders)->toHaveCount(1)
        ->and($orders->first()->idempotency_key)->toBe('second-try')
        ->and($orders->first()->is_paid)->toBeTrue()
        ->and(Order::query()->whereKey($abandoned->id)->exists())->toBeFalse()
        ->and(Promotion::query()->value('uses'))->toBe(1);
});

it('sends the guest to the order a late 3-D Secure already paid, without charging again', function () {
    $context = createCheckoutTestContext();
    createCheckoutTestLine(context: $context, qty: 1, unitPrice: 5570);
    $cookie = $context['session']->token;

    $this->withCookie('qresto_table_session', $cookie)
        ->post(route('checkout.store'), threeDsCheckout('late-3ds'))
        ->assertRedirect(route('checkout'));

    $order = Order::query()->where('idempotency_key', 'late-3ds')->firstOrFail();

    // The bank's challenge completes in another tab; this page never hears.
    app(FakeGateway::class)->confirm(
        (string) $order->payments()->value('gateway_intent_id'),
        ['three_ds_complete' => true],
    );

    $this->withCookie('qresto_table_session', $cookie)
        ->post(route('checkout.store'), threeDsCheckout('retry', FakeGateway::SUCCESS_CARD))
        ->assertRedirect();

    expect(Order::query()->where('table_session_id', $context['session']->id)->count())->toBe(1)
        ->and($order->fresh()->is_paid)->toBeTrue()
        ->and($context['cart']->lines()->count())->toBe(0);
});
