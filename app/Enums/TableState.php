<?php

namespace App\Enums;

enum TableState: string
{
    case FREE = 'free';
    case SEATED = 'seated';
    case ORDERED = 'ordered';
    case BILL = 'bill';

    public function label(): string
    {
        return match ($this) {
            self::FREE => 'Free',
            self::SEATED => 'Seated',
            self::ORDERED => 'Ordered',
            self::BILL => 'Bill',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FREE => 'success',
            self::SEATED => 'info',
            self::ORDERED => 'saffron',
            self::BILL => 'berry',
        };
    }
}