<?php

namespace App\Payments;

use App\Support\Money;

/**
 * QResto stores every amount in hundredths of the currency. Stripe wants
 * the currency's own smallest unit, which for the three-decimal currencies
 * (Kuwaiti and Jordanian dinar among them) is a thousandth. Sending
 * hundredths there would charge a tenth of the bill.
 *
 * Every amount that crosses the Stripe boundary goes through here.
 */
final class StripeAmount
{
    /**
     * Stripe's three-decimal currencies. Stripe also requires their amounts
     * to end in 0, which multiplying hundredths by ten always does.
     */
    private const THREE_DECIMAL = ['BHD', 'JOD', 'KWD', 'OMR', 'TND'];

    public static function toStripe(Money $money): int
    {
        return $money->amount() * self::factor($money->currency());
    }

    public static function fromStripe(int $amount, string $currency): int
    {
        return intdiv($amount, self::factor($currency));
    }

    private static function factor(string $currency): int
    {
        return in_array(strtoupper($currency), self::THREE_DECIMAL, true) ? 10 : 1;
    }
}
