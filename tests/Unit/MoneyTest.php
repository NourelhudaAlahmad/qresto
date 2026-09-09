<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_from_minor_creates_exact_money(): void
    {
        $money = Money::fromMinor(1950, 'USD');

        $this->assertSame(1950, $money->amount());
        $this->assertSame('USD', $money->currency());
        $this->assertSame('19.50', $money->formatted());
    }

    public function test_from_decimal_creates_exact_minor_units(): void
    {
        $money = Money::fromDecimal('19.50', 'USD');

        $this->assertSame(1950, $money->amount());
        $this->assertSame('USD', $money->currency());
        $this->assertSame('19.50', $money->formatted());
    }

    public function test_add_is_exact(): void
    {
        $first = Money::fromDecimal('19.50', 'USD');
        $second = Money::fromDecimal('12.50', 'USD');

        $result = $first->add($second);

        $this->assertSame(3200, $result->amount());
        $this->assertSame('32.00', $result->formatted());
    }

    public function test_subtract_is_exact(): void
    {
        $first = Money::fromDecimal('25.00', 'USD');
        $second = Money::fromDecimal('5.50', 'USD');

        $result = $first->subtract($second);

        $this->assertSame(1950, $result->amount());
        $this->assertSame('19.50', $result->formatted());
    }

    public function test_multiply_is_exact(): void
    {
        $money = Money::fromDecimal('19.50', 'USD');

        $result = $money->multiply(2);

        $this->assertSame(3900, $result->amount());
        $this->assertSame('39.00', $result->formatted());
    }

    public function test_percentage_uses_documented_rounding(): void
    {
        $money = Money::fromDecimal('61.50', 'USD');

        $result = $money->percentage('12.5');

        $this->assertSame(769, $result->amount());
        $this->assertSame('7.69', $result->formatted());
        $this->assertSame('USD', $result->currency());
    }

    public function test_binary_operations_reject_different_currencies(): void
    {
        $usd = Money::fromDecimal('10.00', 'USD');
        $try = Money::fromDecimal('10.00', 'TRY');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Cannot operate on different currencies.',
        );

        $usd->add($try);
    }

    public function test_json_serialize_returns_expected_shape(): void
    {
        $money = Money::fromDecimal('1234.56', 'USD');

        $this->assertSame(
            [
                'amount' => 123456,
                'currency' => 'USD',
                'formatted' => '1234.56',
            ],
            $money->jsonSerialize(),
        );
    }
}
