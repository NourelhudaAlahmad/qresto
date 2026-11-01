<?php

namespace App\Services;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\User;

class NavigationBuilder
{
    /**
     * Build the navigation items from the user's role and capability snapshot.
     *
     * @param  array<int, string>  $capabilities
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     href: string,
     *     badge: int|null
     * }>
     */
    public function for(User $user, array $capabilities): array
    {
        $items = $this->withGrantedExtras($this->itemsForRole($user));

        $visibleItems = array_filter(
            $items,
            fn (array $item): bool => $item['capability'] === null
                || in_array($item['capability']->value, $capabilities, true),
        );

        return array_values(
            array_map(
                fn (array $item): array => [
                    'id' => $item['id'],
                    'title' => $item['title'],
                    'href' => $item['href'],
                    'badge' => $item['badge'],
                ],
                $visibleItems,
            ),
        );
    }

    /**
     * @return array<int, array{
     *     id: string,
     *     title: string,
     *     href: string,
     *     badge: int|null,
     *     capability: Capability|null
     * }>
     */
    private function itemsForRole(User $user): array
    {
        $role = $user->getRoleNames()->first();

        return match ($role) {
            Role::WAITER->value => [
                [
                    'id' => 'waiter',
                    'title' => __('staff.nav.my_orders'),
                    'href' => '/app/my-orders',
                    'badge' => null,
                    'capability' => Capability::SEE_OWN_TABLES,
                ],
                [
                    'id' => 'floor',
                    'title' => __('staff.nav.my_tables'),
                    'href' => '/app/floor',
                    'badge' => null,
                    'capability' => Capability::SEE_OWN_TABLES,
                ],
            ],

            Role::MANAGER->value => [
                [
                    'id' => 'manager',
                    'title' => __('staff.nav.overview'),
                    'href' => '/manager/overview',
                    'badge' => null,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
                [
                    'id' => 'waiter',
                    'title' => __('staff.nav.live_orders'),
                    'href' => '/app/orders',
                    'badge' => null,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
                [
                    'id' => 'floor',
                    'title' => __('staff.nav.floor'),
                    'href' => '/app/floor',
                    'badge' => null,
                    'capability' => Capability::SEE_OWN_TABLES,
                ],
                [
                    'id' => 'reports',
                    'title' => __('staff.nav.reports'),
                    'href' => '/app/reports',
                    'badge' => null,
                    'capability' => Capability::FINANCIAL_REPORTS,
                ],
            ],

            Role::ADMIN->value => [
                [
                    'id' => 'admin',
                    'title' => __('staff.nav.system'),
                    'href' => '/app/system',
                    'badge' => null,
                    'capability' => Capability::MANAGE_RESTAURANT_CONFIG,
                ],
                [
                    'id' => 'menu',
                    'title' => __('staff.nav.menu'),
                    'href' => '/app/menu',
                    'badge' => null,
                    'capability' => Capability::EDIT_MENU_PRICES,
                ],
                [
                    'id' => 'reports',
                    'title' => __('staff.nav.reports'),
                    'href' => '/app/reports',
                    'badge' => null,
                    'capability' => Capability::FINANCIAL_REPORTS,
                ],
                [
                    'id' => 'manager',
                    'title' => __('staff.nav.orders_team'),
                    'href' => '/manager/overview',
                    'badge' => null,
                    'capability' => Capability::MANAGE_WAITER_ACCOUNTS,
                ],
            ],

            Role::KITCHEN->value => [
                [
                    'id' => 'kds',
                    'title' => __('staff.nav.pass'),
                    'href' => '/app/pass',
                    'badge' => null,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
                [
                    'id' => 'waiter',
                    'title' => __('staff.nav.order_feed'),
                    'href' => '/app/orders',
                    'badge' => null,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
            ],

            // #34: role-scoped views of the pass and the order list.
            Role::RUNNER->value => [
                [
                    'id' => 'kds',
                    'title' => __('staff.nav.ready_to_collect'),
                    'href' => '/app/pass',
                    'badge' => null,
                    'capability' => Capability::CHANGE_ORDER_STATUS,
                ],
            ],

            Role::CASHIER->value => [
                [
                    'id' => 'waiter',
                    'title' => __('staff.nav.unpaid_bills'),
                    'href' => '/app/orders',
                    'badge' => null,
                    'capability' => Capability::TAKE_PAYMENT,
                ],
            ],

            default => [],
        };
    }

    /**
     * Screens that follow a capability rather than a role. When the matrix
     * grants a role one of these (#28), the matching item joins that role's
     * sidebar on its next sign-in, after the role's own items.
     *
     * @param  array<int, array{id: string, title: string, href: string, badge: int|null, capability: Capability|null}>  $items
     * @return array<int, array{id: string, title: string, href: string, badge: int|null, capability: Capability|null}>
     */
    private function withGrantedExtras(array $items): array
    {
        $extras = [
            [
                'id' => 'manager',
                'title' => __('staff.nav.overview'),
                'href' => '/manager/overview',
                'badge' => null,
                'capability' => Capability::MANAGE_WAITER_ACCOUNTS,
            ],
            [
                'id' => 'menu',
                'title' => __('staff.nav.menu'),
                'href' => '/app/menu',
                'badge' => null,
                'capability' => Capability::EDIT_MENU_PRICES,
            ],
            [
                'id' => 'reports',
                'title' => __('staff.nav.reports'),
                'href' => '/app/reports',
                'badge' => null,
                'capability' => Capability::FINANCIAL_REPORTS,
            ],
            [
                'id' => 'admin',
                'title' => __('staff.nav.system'),
                'href' => '/app/system',
                'badge' => null,
                'capability' => Capability::MANAGE_RESTAURANT_CONFIG,
            ],
        ];

        $hrefs = array_column($items, 'href');

        foreach ($extras as $extra) {
            if (! in_array($extra['href'], $hrefs, true)) {
                $items[] = $extra;
            }
        }

        return $items;
    }
}
