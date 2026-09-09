<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case CARD = 'card';
    case WALLET = 'wallet';
    case CASH = 'cash';
    case POS = 'pos';

    public function label(): string
    {
        return __(
            'payments.methods.'.$this->value,
        );
    }

    public function color(): string
    {
        return match ($this) {
            self::CARD => 'ink',
            self::WALLET => 'teal',
            self::CASH => 'herb',
            self::POS => 'saffron',
        };
    }
}
