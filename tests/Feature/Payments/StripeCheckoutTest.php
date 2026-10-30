<?php

use App\Enums\PaymentStatus;
use App\Models\Cart;
use App\Models\CartLine;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeStripe;

uses(RefreshDatabase::class);

beforeEach(function () {
    $restaurant = Restaurant::factory()->create(['currency' => 'USD', 'service_charge_pct' => '0']);
    $table = RestaurantTable::factory()->create(['restaurant_id' => $restaurant->id, 'number' => '12']);
    $this->session = TableSession::factory()->create([
        'restaurant_id' => $restaurant->id,
        'restaurant_table_id' => $table->id,
        'guest_name' => 'Nour',
        'opened_at' => now(),
        'last_seen_at' => now(),
        'closed_at' => null,
    ]);
    $category = MenuCategory::factory()->create(['restaurant_id' => $restaurant->id, 'is_active' => true]);
    $item = MenuItem::factory()->create([
        'restaurant_id' => $restaurant->id,
        'menu_category_id' => $category->id,
        'price' => Money::fromMinor(2000, 'USD'),
        'is_available' => true,
        'is_scheduled' => false,
    ]);
    $cart = Cart::query()->create([
        'restaurant_id' => $restaurant->id,
        'table_session_id' => $this->session->id,
        'currency' => 'USD',
    ]);
    CartLine::query()->create([
        'cart_id' => $cart->id,
        'menu_item_id' => $item->id,
        'menu_item_variant_id' => null,
        'name_snapshot' => 'Lamb kofta',
        'variant_label_snapshot' => null,
        'unit_price' => Money::fromMinor(2000, 'USD'),
        'variant_price_delta' => Money::fromMinor(0, 'USD'),
        'qty' => 1,
        'line_total' => Money::fromMinor(2000, 'USD'),
    ]);
});

afterEach(fn () => FakeStripe::uninstall());

function useStripe(): FakeStripe
{
    config([
        'qresto.payments.driver' => 'stripe',
        'qresto.payments.stripe.key' => 'pk_test_public',
    ]);

    return FakeStripe::install();
}

function pay(array $fields)
{
    return test()->withCookie('qresto_table_session', test()->session->token)
        ->post(route('checkout.store'), [
            'method' => 'card',
            'tip_pct' => 0,
            'split_ways' => 1,
            'idempotency_key' => 'stripe-checkout-1',
            ...$fields,
        ]);
}

it('gives the page the publishable key and never the secret', function () {
    useStripe();

    $response = test()->withCookie('qresto_table_session', $this->session->token)->get(route('checkout'));

    $response->assertInertia(fn ($page) => $page
        ->where('gateway.driver', 'stripe')
        ->where('gateway.publishable_key', 'pk_test_public'));

    expect($response->getContent())->not->toContain('sk_test_fake');
});

it('refuses raw card numbers when Stripe is on', function () {
    $stripe = useStripe();

    pay(['card_number' => '4242424242424242', 'card_expiry' => '12/30', 'card_cvc' => '123'])
        ->assertSessionHasErrors(['payment_method', 'card_number', 'card_expiry', 'card_cvc']);

    expect($stripe->requests)->toBe([])
        ->and(Order::query()->count())->toBe(0);
});

it('pays with a tokenised card and settles without leaving the app', function () {
    $stripe = useStripe()->respond(FakeStripe::intent('pi_ok', 'succeeded'));

    pay(['payment_method' => 'pm_card_visa'])->assertSessionHasNoErrors();

    $order = Order::query()->firstOrFail();
    $payment = $order->payments()->firstOrFail();

    expect($order->is_paid)->toBeTrue()
        ->and($payment->gateway)->toBe('stripe')
        ->and($payment->gateway_intent_id)->toBe('pi_ok')
        ->and($payment->status)->toBe(PaymentStatus::PAID->value)
        ->and($stripe->requests[0]['params']['payment_method'])->toBe('pm_card_visa');
});

it('shows a decline inline and leaves the order unplaced', function () {
    useStripe()->decline('card_declined', 'generic_decline');

    pay(['payment_method' => 'pm_card_chargeDeclined'])
        ->assertSessionHasErrors(['card_cvc' => 'Your card was declined.']);

    expect(Order::query()->count())->toBe(0);
});

it('runs 3-D Secure in-page: checkout carries the client secret, confirm settles', function () {
    $stripe = useStripe()->respond(FakeStripe::intent('pi_3ds', 'requires_action'));

    pay(['payment_method' => 'pm_card_threeDSecure2Required'])->assertRedirect(route('checkout'));

    $order = Order::query()->firstOrFail();

    test()->withCookie('qresto_table_session', $this->session->token)
        ->get(route('checkout'))
        ->assertInertia(fn ($page) => $page
            ->where('gateway.3ds_required', true)
            ->where('gateway.order_id', $order->id)
            ->where('gateway.client_secret', 'pi_3ds_secret_abc'));

    // The browser completes the challenge with Stripe.js, then confirms.
    $stripe->respond(FakeStripe::intent('pi_3ds', 'succeeded'));

    test()->withCookie('qresto_table_session', $this->session->token)
        ->post('/checkout/confirm', ['order_id' => $order->id, 'confirmed' => true])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->is_paid)->toBeTrue();
});

it('keeps a processing payment pending for the webhook to settle', function () {
    useStripe()->respond(FakeStripe::intent('pi_slow', 'processing'));

    pay(['payment_method' => 'pm_x']);

    $order = Order::query()->firstOrFail();

    expect($order->is_paid)->toBeFalse()
        ->and($order->payments()->first()->status)->toBe(PaymentStatus::PENDING->value);
});
