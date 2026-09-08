<?php

namespace Tests\Feature;

use App\Support\Money;
use App\Support\OrderTotals;
use Tests\TestCase;

class OrderTotalsTest extends TestCase
{
    public function test_order_totals_are_calculated_correctly(): void
    {
        $totals = OrderTotals::calculate([
            [
                'unit_price' => Money::fromDecimal('19.50'),
                'qty' => 2,
            ],
            [
                'unit_price' => Money::fromDecimal('12.50'),
                'qty' => 1,
            ],
        ]);

        $this->assertSame(
            5150,
            $totals->subtotal->amount(),
        );

        $this->assertSame(
            5150,
            $totals->total->amount(),
        );
    }

    public function test_totals_include_tip_and_discount(): void
    {
        $totals = OrderTotals::calculate(
            [
                [
                    'unit_price' => Money::fromDecimal('20.00'),
                    'qty' => 2,
                ],
            ],
            '10',
            Money::fromDecimal('5.00'),
            Money::fromDecimal('3.00'),
        );

        $this->assertSame(
            4000,
            $totals->subtotal->amount(),
        );

        $this->assertSame(
            400,
            $totals->serviceAmount->amount(),
        );

        $this->assertSame(
            4600,
            $totals->total->amount(),
        );
    }

    public function test_empty_order_has_zero_totals(): void
    {
        $totals = OrderTotals::calculate([]);

        $this->assertSame(
            0,
            $totals->subtotal->amount(),
        );

        $this->assertSame(
            0,
            $totals->serviceAmount->amount(),
        );

        $this->assertSame(
            0,
            $totals->total->amount(),
        );
    }

    public function test_discount_can_not_be_ignored(): void
    {
        $totals = OrderTotals::calculate(
            [
                [
                    'unit_price' => Money::fromDecimal('50.00'),
                    'qty' => 1,
                ],
            ],
            '0',
            Money::fromDecimal('0.00'),
            Money::fromDecimal('10.00'),
        );

        $this->assertSame(
            4000,
            $totals->total->amount(),
        );
    }
}