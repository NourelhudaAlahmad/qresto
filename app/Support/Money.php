<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;

final class Money implements JsonSerializable
{
    private function __construct(
        private readonly int $amount,
        private readonly string $currency,
    ) {}

    public static function fromMinor(
        int $amount,
        string $currency = 'TRY',
    ): self {
        return new self(
            $amount,
            strtoupper($currency),
        );
    }

    public static function fromDecimal(
        string $amount,
        string $currency = 'TRY',
    ): self {
        if (! preg_match('/^-?\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException(
                'Invalid decimal money amount.'
            );
        }

        $negative = str_starts_with($amount, '-');
        $normalized = ltrim($amount, '+-');

        [$whole, $decimal] = array_pad(
            explode('.', $normalized, 2),
            2,
            '0',
        );

        $decimal = str_pad($decimal, 2, '0');

        $minor = ((int) $whole * 100) + (int) $decimal;

        return new self(
            $negative ? -$minor : $minor,
            strtoupper($currency),
        );
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromMinor(
            $this->amount + $other->amount,
            $this->currency,
        );
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromMinor(
            $this->amount - $other->amount,
            $this->currency,
        );
    }

    public function multiply(int $multiplier): self
    {
        return self::fromMinor(
            $this->amount * $multiplier,
            $this->currency,
        );
    }

    public function percentage(string $percentage): self
    {
        if (! preg_match('/^\d+(?:\.\d+)?$/', $percentage)) {
            throw new InvalidArgumentException(
                'Invalid percentage.'
            );
        }

        [$whole, $decimal] = array_pad(
            explode('.', $percentage, 2),
            2,
            '',
        );

        $scale = 10 ** strlen($decimal);

        $percentageValue =
            ((int) $whole * $scale) +
            (int) ($decimal === '' ? 0 : $decimal);

        $numerator = $this->amount * $percentageValue;
        $denominator = 100 * $scale;

        $minor = intdiv(
            $numerator + intdiv($denominator, 2),
            $denominator,
        );

        return self::fromMinor(
            $minor,
            $this->currency,
        );
    }

    public function formatted(): string
    {
        return number_format(
            $this->amount / 100,
            2,
            '.',
            '',
        );
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException(
                'Cannot operate on different currencies.'
            );
        }
    }

    /**
     * @return array<string, int|string>
     */
    public function jsonSerialize(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'formatted' => $this->formatted(),
        ];
    }
}
