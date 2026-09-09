<?php

namespace App\Support;

final class OrderTotals
{
    public function __construct(
        public readonly Money $subtotal,
        public readonly Money $serviceAmount,
        public readonly Money $total,
    ) {}

    /**
     * @param  array<int, array{unit_price: Money, qty: int}>  $lines
     */
    public static function calculate(
        array $lines,
        string $servicePct = '0',
        ?Money $tipAmount = null,
        ?Money $discountAmount = null,
    ): self {
        if ($lines === []) {
            $currency = $tipAmount?->currency()
                ?? $discountAmount?->currency()
                ?? 'TRY';

            $subtotal = Money::fromMinor(0, $currency);
        } else {
            $firstPrice = $lines[array_key_first($lines)]['unit_price'];

            $subtotal = Money::fromMinor(
                0,
                $firstPrice->currency(),
            );
        }

        foreach ($lines as $line) {
            $subtotal = $subtotal->add(
                $line['unit_price']->multiply((int) $line['qty']),
            );
        }

        $tipAmount ??= Money::fromMinor(
            0,
            $subtotal->currency(),
        );

        $discountAmount ??= Money::fromMinor(
            0,
            $subtotal->currency(),
        );

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
