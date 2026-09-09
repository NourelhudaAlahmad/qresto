<?php

namespace App\Enums;

enum ServiceRequestKind: string
{
    case WATER = 'water';
    case BREAD = 'bread';
    case BILL = 'bill';
    case WAITER = 'waiter';
    case CLEANING = 'cleaning';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WATER => 'Water',
            self::BREAD => 'Bread',
            self::BILL => 'Bill',
            self::WAITER => 'Waiter',
            self::CLEANING => 'Cleaning',
            self::OTHER => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::WATER => 'info',
            self::BREAD => 'herb',
            self::BILL => 'saffron',
            self::WAITER => 'teal',
            self::CLEANING => 'herb',
            self::OTHER => 'clay',
        };
    }
}