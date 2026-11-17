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
                'promo_code' => __('messages.promo.invalid'),
            ]);
        }

        if (
            $promotion->starts_at !== null
            && $promotion->starts_at->isFuture()
        ) {
            throw ValidationException::withMessages([
                'promo_code' => __('messages.promo.not_active'),
            ]);
        }

        if (
            $promotion->ends_at !== null
            && $promotion->ends_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'promo_code' => __('messages.promo.expired'),
            ]);
        }

        if (
            $promotion->max_uses !== null
            && $promotion->uses >= $promotion->max_uses
        ) {
            throw ValidationException::withMessages([
                'promo_code' => __('messages.promo.limit_reached'),
            ]);
        }

        $discount = match ($promotion->kind) {
            'percent' => $subtotal->percentage((string) $promotion->value),

            'amount' => Money::fromDecimal(
                (string) $promotion->value,
                $subtotal->currency(),
            ),

            default => throw ValidationException::withMessages([
                'promo_code' => __('messages.promo.invalid'),
            ]),
        };

        if ($discount->amount() > $subtotal->amount()) {
            return $subtotal;
        }

        return $discount;
    }

    /**
     * Take one use of the code. The limit is checked in the same UPDATE
     * that counts the use, so two guests racing for the last use cannot
     * both get it: the loser is told the limit is reached.
     */
    public function consume(
        int $restaurantId,
        ?string $code,
    ): void {
        if ($code === null || trim($code) === '') {
            return;
        }

        $claimed = Promotion::query()
            ->where('restaurant_id', $restaurantId)
            ->where('code', strtoupper(trim($code)))
            ->where(fn ($query) => $query
                ->whereNull('max_uses')
                ->orWhereColumn('uses', '<', 'max_uses'))
            ->increment('uses');

        if ($claimed === 0) {
            throw ValidationException::withMessages([
                'promo_code' => __('messages.promo.limit_reached'),
            ]);
        }
    }

    /**
     * Give back a use taken for a payment that then failed.
     */
    public function release(
        int $restaurantId,
        ?string $code,
    ): void {
        if ($code === null || trim($code) === '') {
            return;
        }

        Promotion::query()
            ->where('restaurant_id', $restaurantId)
            ->where('code', strtoupper(trim($code)))
            ->where('uses', '>', 0)
            ->decrement('uses');
    }
}
