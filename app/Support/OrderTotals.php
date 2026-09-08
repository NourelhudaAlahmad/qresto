<?php

namespace App\Support;

use InvalidArgumentException;

final class OrderTotals
{
    public function __construct(
        public readonly Money $subtotal,
        public readonly Money $serviceAmount,
        public readonly Money $total,
    ) {}

    /**
     * @param array<int, array{unit_price: Money, qty: int}> $lines
     */
    public static function calculate(
        array $lines,
        string $servicePct = '0',
        ?Money $tipAmount = null,
        ?Money $discountAmount = null,
    ): self {
        $tipAmount ??= Money::fromMinor(0);
        $discountAmount ??= Money::fromMinor(0);

        $subtotal = Money::fromMinor(0);

        foreach ($lines as $line) {
            if (! $line['unit_price'] instanceof Money) {
                throw new InvalidArgumentException(
                    'Line unit_price must be an instance of Money.'
                );
            }

            $subtotal = $subtotal->add(
                $line['unit_price']->multiply((int) $line['qty']),
            );
        }

        $serviceAmount = $subtotal->percentage($servicePct);

        $total = $subtotal
            ->add($serviceAmount)
            ->add($tipAmount)
            ->subtract($discountAmount);

        return new self(
            subtotal: $subtotal,
            serviceAmount: $serviceAmount,
            total: $total,
        );
    }
}