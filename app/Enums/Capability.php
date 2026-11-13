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
        return __('staff.admin_system.capabilities.'.$this->value);
    }

    public function color(): string
    {
        return 'ink';
    }
}
