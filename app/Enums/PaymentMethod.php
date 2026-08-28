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
        return match ($this) {
            self::CARD => 'Card',
            self::WALLET => 'Wallet',
            self::CASH => 'Cash',
            self::POS => 'POS',
        };
    }

    public function color(): string
    {
        return 'ink';
    }
}
