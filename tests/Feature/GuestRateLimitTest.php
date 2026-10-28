<?php

use App\Enums\TableState;
use App\Models\Restaurant;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    RateLimiter::clear('sr:');

    $restaurant = Restaurant::factory()->create();

    $table = RestaurantTable::factory()
        ->forRestaurant($restaurant)
        ->create(['state' => TableState::SEATED]);

    $this->session = TableSession::openFor($table);
});

it('puts an outer cap on calling the waiter, across every kind', function () {
    // The controller already refuses a second open request of the same
    // kind; the limiter caps the total, whatever the kind.
    $response = $this
        ->withCookie('qresto_table_session', $this->session->token)
        ->post(route('guest.service-requests.store'), ['kind' => 'water'])
        ->assertStatus(302);

    expect($response->headers->get('X-RateLimit-Limit'))->toBe('6');

    foreach (range(1, 5) as $tap) {
        $this->withCookie('qresto_table_session', $this->session->token)
            ->post(route('guest.service-requests.store'), ['kind' => 'water']);
    }

    $this->withCookie('qresto_table_session', $this->session->token)
        ->post(route('guest.service-requests.store'), ['kind' => 'bread'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');

    $this->travel(61)->seconds();

    $this->withCookie('qresto_table_session', $this->session->token)
        ->post(route('guest.service-requests.store'), ['kind' => 'bread'])
        ->assertStatus(302);
});

it('caps promo code attempts so codes cannot be guessed', function () {
    $statuses = [];

    foreach (range(1, 7) as $attempt) {
        $statuses[] = $this
            ->withCookie('qresto_table_session', $this->session->token)
            ->post(route('checkout.promo.store'), ['promo_code' => "GUESS{$attempt}"])
            ->getStatusCode();
    }

    expect(array_slice($statuses, 0, 5))->not->toContain(429)
        ->and(array_slice($statuses, 5))->toBe([429, 429]);
});

it('keeps one table\'s limit separate from another\'s', function () {
    $other = TableSession::openFor(
        RestaurantTable::factory()
            ->forRestaurant(Restaurant::query()->firstOrFail())
            ->create(['state' => TableState::SEATED]),
    );

    foreach (range(1, 7) as $tap) {
        $this->withCookie('qresto_table_session', $this->session->token)
            ->post(route('guest.service-requests.store'), ['kind' => 'water']);
    }

    $this->withCookie('qresto_table_session', $other->token)
        ->post(route('guest.service-requests.store'), ['kind' => 'water'])
        ->assertStatus(302);
});
