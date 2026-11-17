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

    /**
     * Roles that a manager's shift QR can never sign in. A shift QR skips
     * the password, so it must not open an account that outranks the
     * manager who issued it.
     *
     * @return list<string>
     */
    public static function excludedFromShiftQr(): array
    {
        return [self::MANAGER->value, self::ADMIN->value];
    }

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
