<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case REQUIRES_ACTION = 'requires_action';
    case PAID = 'paid';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::REQUIRES_ACTION => 'Requires action',
            self::PAID => 'Paid',
            self::FAILED => 'Failed',
            self::REFUNDED => 'Refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'saffron',
            self::REQUIRES_ACTION => 'saffron',
            self::PAID => 'success',
            self::FAILED => 'berry',
            self::REFUNDED => 'info',
        };
    }
}