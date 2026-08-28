<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PLACED = 'placed';
    case PENDING = 'pending';
    case PREPARING = 'preparing';
    case READY = 'ready';
    case SERVED = 'served';
    case PAID = 'paid';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PLACED => 'Placed',
            self::PENDING => 'Pending',
            self::PREPARING => 'Preparing',
            self::READY => 'Ready',
            self::SERVED => 'Served',
            self::PAID => 'Paid',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PLACED => 'info',
            self::PENDING => 'info',
            self::PREPARING => 'saffron',
            self::READY => 'success',
            self::SERVED => 'success',
            self::PAID => 'success',
            self::CANCELLED => 'berry',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::PLACED => in_array($next, [
                self::PENDING,
                self::CANCELLED,
            ], true),

            self::PENDING => in_array($next, [
                self::PREPARING,
                self::CANCELLED,
            ], true),

            self::PREPARING => in_array($next, [
                self::READY,
                self::CANCELLED,
            ], true),

            self::READY => $next === self::SERVED,

            self::SERVED => $next === self::PAID,

            self::PAID,
            self::CANCELLED => false,
        };
    }
}