<?php

namespace App\Enums;

enum ServiceRequestKind: string
{
    case WATER = 'water';
    case CUTLERY = 'cutlery';
    case BILL = 'bill';
    case WAITER = 'waiter';
    case CLEANING = 'cleaning';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::WATER => 'Water',
            self::CUTLERY => 'Cutlery',
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
            self::CUTLERY => 'ink',
            self::BILL => 'saffron',
            self::WAITER => 'teal',
            self::CLEANING => 'herb',
            self::OTHER => 'clay',
        };
    }
}