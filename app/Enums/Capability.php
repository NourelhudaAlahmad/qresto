<?php

namespace App\Enums;

enum Capability: string
{
    case SEE_OWN_TABLES = 'see_own_tables';
    case SEE_ALL_ORDERS = 'see_all_orders';
    case CHANGE_ORDER_STATUS = 'change_order_status';
    case TAKE_PAYMENT = 'take_payment';
    case MANAGE_WAITER_ACCOUNTS = 'manage_waiter_accounts';
    case EDIT_MENU_PRICES = 'edit_menu_prices';
    case FINANCIAL_REPORTS = 'financial_reports';
    case MANAGE_RESTAURANT_CONFIG = 'manage_restaurant_config';

    public function label(): string
    {
        return match ($this) {
            self::SEE_OWN_TABLES => 'See own tables',
            self::SEE_ALL_ORDERS => 'See all orders',
            self::CHANGE_ORDER_STATUS => 'Change order status',
            self::TAKE_PAYMENT => 'Take payment · refund',
            self::MANAGE_WAITER_ACCOUNTS => 'Manage waiter accounts',
            self::EDIT_MENU_PRICES => 'Edit menu & prices',
            self::FINANCIAL_REPORTS => 'Financial reports · export',
            self::MANAGE_RESTAURANT_CONFIG => 'Restaurant & system config',
        };
    }

    public function color(): string
    {
        return 'ink';
    }
}
