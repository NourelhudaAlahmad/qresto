<?php

namespace App\Enums;

enum Role: string
{
    case WAITER = 'waiter';
    case MANAGER = 'manager';
    case ADMIN = 'admin';
    case KITCHEN = 'kitchen';
    case RUNNER = 'runner';
    case CASHIER = 'cashier';

    public function label(): string
    {
        return __('staff.roles.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::WAITER => 'teal',
            self::MANAGER => 'clay',
            self::ADMIN => 'ink',
            self::KITCHEN => 'saffron',
            self::RUNNER => 'herb',
            self::CASHIER => 'ink',
        };
    }
}
