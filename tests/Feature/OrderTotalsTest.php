<?php

namespace Tests\Feature;

use App\Support\OrderTotals;
use PHPUnit\Framework\TestCase;

class OrderTotalsTest extends TestCase
{
    public function test_order_totals_are_calculated_correctly(): void
    {
        $totals = OrderTotals::calculate(
            lines: [
                [
                    'unit_price' => 19.50,
                    'qty' => 2,
                ],
                [
                    'unit_price' => 12.50,
                    'qty' => 1,
                ],
                [
                    'unit_price' => 5.00,
                    'qty' => 2,
                ],
            ],
            servicePct: 12.5,
        );

        $this->assertSame(61.50, $totals->subtotal);
        $this->assertSame(7.69, $totals->serviceAmount);
        $this->assertSame(69.19, $totals->total);
    }

    public function test_totals_include_tip_and_discount(): void
    {
        $totals = OrderTotals::calculate(
            lines: [
                [
                    'unit_price' => 20.00,
                    'qty' => 2,
                ],
            ],
            servicePct: 10,
            tipAmount: 5,
            discountAmount: 3,
        );

        $this->assertSame(40.00, $totals->subtotal);
        $this->assertSame(4.00, $totals->serviceAmount);
        $this->assertSame(46.00, $totals->total);
    }

    public function test_empty_order_has_zero_totals(): void
    {
        $totals = OrderTotals::calculate([]);

        $this->assertSame(0.00, $totals->subtotal);
        $this->assertSame(0.00, $totals->serviceAmount);
        $this->assertSame(0.00, $totals->total);
    }

    public function test_discount_can_not_be_ignored(): void
    {
        $totals = OrderTotals::calculate(
            lines: [
                [
                    'unit_price' => 50.00,
                    'qty' => 1,
                ],
            ],
            discountAmount: 10,
        );

        $this->assertSame(50.00, $totals->subtotal);
        $this->assertSame(0.00, $totals->serviceAmount);
        $this->assertSame(40.00, $totals->total);
    }
}
