<?php

use App\Models\Order;
use App\Models\Promotion;
use App\Payments\Gateways\FakeGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * A guest reaches 3-D Secure and walks away, never to check out again.
 */
function abandonAtThreeDs(array $context, string $key): Order
{
    test()->withCookie('qresto_table_session', $context['session']->token)
        ->post(route('checkout.store'), threeDsCheckout($key))
        ->assertRedirect(route('checkout'));

    return Order::query()->where('idempotency_key', $key)->firstOrFail();
}

it('discards a card order left at 3-D Secure past the limit and gives its promo back', function () {
    config(['qresto.abandoned_checkout_minutes' => 30]);

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

    test()->withCookie('qresto_table_session', $context['session']->token)
        ->withSession(['checkout.promo_code' => 'SAVE10'])
        ->post(route('checkout.store'), threeDsCheckout('walked-away'))
        ->assertRedirect(route('checkout'));

    $order = Order::query()->where('idempotency_key', 'walked-away')->firstOrFail();

    $this->travel(31)->minutes();

    $this->artisan('qresto:expire-abandoned-checkouts')->assertSuccessful();

    expect(Order::query()->whereKey($order->id)->exists())->toBeFalse()
        ->and(Promotion::query()->value('uses'))->toBe(0)
        // The guest's cart is untouched: they can still order.
        ->and($context['cart']->lines()->count())->toBe(1);
});

it('leaves a 3-D Secure order alone while the guest may still finish it', function () {
    config(['qresto.abandoned_checkout_minutes' => 30]);

    $context = createCheckoutTestContext();
    createCheckoutTestLine(context: $context, qty: 1, unitPrice: 5570);

    $order = abandonAtThreeDs($context, 'still-verifying');

    $this->travel(10)->minutes();

    $this->artisan('qresto:expire-abandoned-checkouts')->assertSuccessful();

    expect(Order::query()->whereKey($order->id)->exists())->toBeTrue();
});

it('finishes an abandoned order whose 3-D Secure was completed meanwhile', function () {
    config(['qresto.abandoned_checkout_minutes' => 30]);

    $context = createCheckoutTestContext();
    createCheckoutTestLine(context: $context, qty: 1, unitPrice: 5570);

    $order = abandonAtThreeDs($context, 'paid-elsewhere');

    // The bank's challenge completed in another tab; nothing told the app.
    app(FakeGateway::class)->confirm(
        (string) $order->payments()->value('gateway_intent_id'),
        ['three_ds_complete' => true],
    );

    $this->travel(31)->minutes();

    $this->artisan('qresto:expire-abandoned-checkouts')->assertSuccessful();

    expect($order->fresh()->is_paid)->toBeTrue()
        ->and($context['cart']->lines()->count())->toBe(0);
});

it('never touches an order that was paid', function () {
    config(['qresto.abandoned_checkout_minutes' => 30]);

    $context = createCheckoutTestContext();
    createCheckoutTestLine(context: $context, qty: 1, unitPrice: 5570);

    test()->withCookie('qresto_table_session', $context['session']->token)
        ->post(route('checkout.store'), threeDsCheckout('paid-at-once', FakeGateway::SUCCESS_CARD))
        ->assertSessionHasNoErrors();

    $this->travel(2)->hours();

    $this->artisan('qresto:expire-abandoned-checkouts')->assertSuccessful();

    expect(Order::query()->where('idempotency_key', 'paid-at-once')->first()?->is_paid)->toBeTrue();
});
