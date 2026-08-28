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
        return match ($this) {
            self::WAITER => 'Waiter',
            self::MANAGER => 'Manager',
            self::ADMIN => 'Super admin',
            self::KITCHEN => 'Kitchen',
            self::RUNNER => 'Runner',
            self::CASHIER => 'Cashier',
        };
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