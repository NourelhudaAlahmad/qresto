<?php

use App\Actions\Payments\PromotionValidator;
use App\Models\Promotion;
use App\Models\Restaurant;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('calculates a percent promotion discount', function (): void {
    $restaurant = Restaurant::factory()->create();

    Promotion::factory()
        ->forRestaurant($restaurant)
        ->active()
        ->percent(10)
        ->create([
            'code' => 'SAVE10',
        ]);

    $discount = app(PromotionValidator::class)->validate(
        restaurantId: $restaurant->id,
        code: 'SAVE10',
        subtotal: Money::fromMinor(10000, $restaurant->currency),
    );

    expect($discount->amount())->toBe(1000);
});

it('calculates a fixed amount promotion discount', function (): void {
    $restaurant = Restaurant::factory()->create();

    Promotion::factory()
        ->forRestaurant($restaurant)
        ->active()
        ->amount(7.50)
        ->create([
            'code' => 'SAVE750',
        ]);

    $discount = app(PromotionValidator::class)->validate(
        restaurantId: $restaurant->id,
        code: 'SAVE750',
        subtotal: Money::fromMinor(10000, $restaurant->currency),
    );

    expect($discount->amount())->toBe(750);
});

it('caps the discount at the subtotal', function (): void {
    $restaurant = Restaurant::factory()->create();

    Promotion::factory()
        ->forRestaurant($restaurant)
        ->active()
        ->amount(50)
        ->create([
            'code' => 'BIGSAVE',
        ]);

    $subtotal = Money::fromMinor(1000, $restaurant->currency);

    $discount = app(PromotionValidator::class)->validate(
        restaurantId: $restaurant->id,
        code: 'BIGSAVE',
        subtotal: $subtotal,
    );

    expect($discount->amount())->toBe(1000);
});

it('rejects an expired promotion', function (): void {
    $restaurant = Restaurant::factory()->create();

    Promotion::factory()
        ->forRestaurant($restaurant)
        ->expired()
        ->percent(10)
        ->create([
            'code' => 'EXPIRED10',
        ]);

    app(PromotionValidator::class)->validate(
        restaurantId: $restaurant->id,
        code: 'EXPIRED10',
        subtotal: Money::fromMinor(10000, $restaurant->currency),
    );
})->throws(
    ValidationException::class,
    'This promo code has expired.',
);

it('rejects an over-used promotion', function (): void {
    $restaurant = Restaurant::factory()->create();

    Promotion::factory()
        ->forRestaurant($restaurant)
        ->active()
        ->percent(10)
        ->create([
            'code' => 'USED10',
            'max_uses' => 5,
            'uses' => 5,
        ]);

    app(PromotionValidator::class)->validate(
        restaurantId: $restaurant->id,
        code: 'USED10',
        subtotal: Money::fromMinor(10000, $restaurant->currency),
    );
})->throws(
    ValidationException::class,
    'This promo code has reached its usage limit.',
);

it('rejects a promotion belonging to another restaurant', function (): void {
    $restaurant = Restaurant::factory()->create();
    $otherRestaurant = Restaurant::factory()->create();

    Promotion::factory()
        ->forRestaurant($otherRestaurant)
        ->active()
        ->percent(10)
        ->create([
            'code' => 'OTHER10',
        ]);

    app(PromotionValidator::class)->validate(
        restaurantId: $restaurant->id,
        code: 'OTHER10',
        subtotal: Money::fromMinor(10000, $restaurant->currency),
    );
})->throws(
    ValidationException::class,
    'This promo code is invalid.',
);

it('increments promotion usage when consumed', function (): void {
    $restaurant = Restaurant::factory()->create();

    $promotion = Promotion::factory()
        ->forRestaurant($restaurant)
        ->active()
        ->percent(10)
        ->create([
            'code' => 'USEME',
            'uses' => 0,
        ]);

    app(PromotionValidator::class)->consume(
        restaurantId: $restaurant->id,
        code: 'USEME',
    );

    expect($promotion->fresh()->uses)->toBe(1);
});
