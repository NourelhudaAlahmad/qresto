<?php

use App\Models\Order;
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
