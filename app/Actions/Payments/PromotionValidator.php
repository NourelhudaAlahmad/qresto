<?php

namespace App\Actions\Payments;

use App\Models\Promotion;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

final class PromotionValidator
{
    public function validate(
        int $restaurantId,
        ?string $code,
        Money $subtotal,
    ): Money {
        if ($code === null || trim($code) === '') {
            return Money::fromMinor(0, $subtotal->currency());
        }

        $promotion = Promotion::query()
            ->where('restaurant_id', $restaurantId)
            ->where('code', strtoupper(trim($code)))
            ->first();

        if ($promotion === null) {
            throw ValidationException::withMessages([
                'promo_code' => 'This promo code is invalid.',
            ]);
        }

        if (
            $promotion->starts_at !== null
            && $promotion->starts_at->isFuture()
        ) {
            throw ValidationException::withMessages([
                'promo_code' => 'This promo code is not active yet.',
            ]);
        }

        if (
            $promotion->ends_at !== null
            && $promotion->ends_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'promo_code' => 'This promo code has expired.',
            ]);
        }

        if (
            $promotion->max_uses !== null
            && $promotion->uses >= $promotion->max_uses
        ) {
            throw ValidationException::withMessages([
                'promo_code' => 'This promo code has reached its usage limit.',
            ]);
        }

        $discount = match ($promotion->kind) {
            'percent' => $subtotal->percentage((string) $promotion->value),

            'amount' => Money::fromDecimal(
                (string) $promotion->value,
                $subtotal->currency(),
            ),

            default => throw ValidationException::withMessages([
                'promo_code' => 'This promo code is invalid.',
            ]),
        };

        if ($discount->amount() > $subtotal->amount()) {
            return $subtotal;
        }

        return $discount;
    }

    public function consume(
        int $restaurantId,
        ?string $code,
    ): void {
        if ($code === null || trim($code) === '') {
            return;
        }

        Promotion::query()
            ->where('restaurant_id', $restaurantId)
            ->where('code', strtoupper(trim($code)))
            ->increment('uses');
    }
}
