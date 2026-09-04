<?php

namespace App\Support;

final class OrderTotals
{
    public function __construct(
        public readonly float $subtotal,
        public readonly float $serviceAmount,
        public readonly float $total,
    ) {}

    /**
     * @param  array<int, array{unit_price: float|int|string, qty: int}>  $lines
     */
    public static function calculate(
        array $lines,
        float $servicePct = 0,
        float $tipAmount = 0,
        float $discountAmount = 0,
    ): self {
        $subtotal = 0.0;

        foreach ($lines as $line) {
            $subtotal += (float) $line['unit_price'] * (int) $line['qty'];
        }

        $subtotal = round($subtotal, 2);

        $serviceAmount = round(
            $subtotal * $servicePct / 100,
            2,
            PHP_ROUND_HALF_UP,
        );

        $total = round(
            $subtotal
                + $serviceAmount
                + $tipAmount
                - $discountAmount,
            2,
            PHP_ROUND_HALF_UP,
        );

        return new self(
            subtotal: $subtotal,
            serviceAmount: $serviceAmount,
            total: $total,
        );
    }
}
