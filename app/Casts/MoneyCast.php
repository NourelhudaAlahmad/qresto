<?php

namespace App\Casts;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * @implements CastsAttributes<Money, mixed>
 */
class MoneyCast implements CastsAttributes
{
    public function get(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?Money {
        if ($value === null) {
            return null;
        }

        return Money::fromMinor(
            (int) $value,
            $this->resolveCurrency($model, $attributes),
        );
    }

    public function set(
        Model $model,
        string $key,
        mixed $value,
        array $attributes,
    ): ?int {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->amount();
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return Money::fromDecimal(
                $value,
                $this->resolveCurrency($model, $attributes),
            )->amount();
        }

        throw new InvalidArgumentException(
            'MoneyCast expects a Money object, integer, or decimal string.',
        );
    }

    /**
     * Resolve the currency from the model itself first,
     * then from its restaurant relation.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function resolveCurrency(
        Model $model,
        array $attributes,
    ): string {
        if (
            array_key_exists('currency', $attributes)
            && is_string($attributes['currency'])
            && $attributes['currency'] !== ''
        ) {
            return strtoupper($attributes['currency']);
        }

        $currency = $model->getAttribute('currency');

        if (is_string($currency) && $currency !== '') {
            return strtoupper($currency);
        }

        if (
            method_exists($model, 'restaurant')
            && $model->relationLoaded('restaurant')
        ) {
            $restaurant = $model->getRelation('restaurant');

            if (
                $restaurant !== null
                && is_string($restaurant->currency)
                && $restaurant->currency !== ''
            ) {
                return strtoupper($restaurant->currency);
            }
        }

        if (
            method_exists($model, 'restaurant')
            && $model instanceof Order
        ) {
            $restaurant = $model->restaurant;

            if (
                $restaurant !== null
                && is_string($restaurant->currency)
                && $restaurant->currency !== ''
            ) {
                return strtoupper($restaurant->currency);
            }
        }

        return 'TRY';
    }
}