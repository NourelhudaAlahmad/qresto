<?php

namespace App\Casts;

use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class MoneyCast implements CastsAttributes
{
    /**
     * Convert the stored integer into a Money value object.
     *
     * @param array<string, mixed> $attributes
     */
    public function get(
        Model $model,
        string $key,
        mixed $value,
        array $attributes
    ): ?Money {
        if ($value === null) {
            return null;
        }

        return Money::fromMinor(
            (int) $value,
            $model->getAttribute('currency') ?? 'TRY',
        );
    }

    /**
     * Convert a Money value object into minor units for storage.
     *
     * @param array<string, mixed> $attributes
     */
    public function set(
        Model $model,
        string $key,
        mixed $value,
        array $attributes
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
                $model->getAttribute('currency') ?? 'TRY',
            )->amount();
        }

        throw new \InvalidArgumentException(
            'MoneyCast expects a Money object, integer, or decimal string.',
        );
    }
}

