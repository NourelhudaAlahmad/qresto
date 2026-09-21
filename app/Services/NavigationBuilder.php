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
        $items = $this->itemsForRole($user);

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
                    'title' => 'My orders',
                    'href' => '/orders',
                    'badge' => 4,
                    'capability' => Capability::SEE_OWN_TABLES,
                ],
                [
                    'id' => 'floor',
                    'title' => 'My tables',
                    'href' => '/tables',
                    'badge' => null,
                    'capability' => Capability::SEE_OWN_TABLES,
                ],
                [
                    'id' => 'auth',
                    'title' => 'Sign in screen',
                    'href' => '/login',
                    'badge' => null,
                    'capability' => null,
                ],
            ],

            Role::MANAGER->value => [
                [
                    'id' => 'manager',
                    'title' => 'Overview',
                    'href' => '/dashboard',
                    'badge' => null,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
                [
                    'id' => 'waiter',
                    'title' => 'Live orders',
                    'href' => '/orders',
                    'badge' => 7,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
                [
                    'id' => 'floor',
                    'title' => 'Floor',
                    'href' => '/tables',
                    'badge' => null,
                    'capability' => Capability::SEE_OWN_TABLES,
                ],
                [
                    'id' => 'reports',
                    'title' => 'Reports',
                    'href' => '/reports',
                    'badge' => null,
                    'capability' => Capability::FINANCIAL_REPORTS,
                ],
                [
                    'id' => 'auth',
                    'title' => 'Sign in screen',
                    'href' => '/login',
                    'badge' => null,
                    'capability' => null,
                ],
            ],

            Role::ADMIN->value => [
                [
                    'id' => 'admin',
                    'title' => 'System',
                    'href' => '/settings',
                    'badge' => null,
                    'capability' => Capability::MANAGE_RESTAURANT_CONFIG,
                ],
                [
                    'id' => 'menu',
                    'title' => 'Menu',
                    'href' => '/menu',
                    'badge' => null,
                    'capability' => Capability::EDIT_MENU_PRICES,
                ],
                [
                    'id' => 'reports',
                    'title' => 'Reports',
                    'href' => '/reports',
                    'badge' => null,
                    'capability' => Capability::FINANCIAL_REPORTS,
                ],
                [
                    'id' => 'manager',
                    'title' => 'Orders & team',
                    'href' => '/orders',
                    'badge' => null,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
                [
                    'id' => 'auth',
                    'title' => 'Sign in screen',
                    'href' => '/login',
                    'badge' => null,
                    'capability' => null,
                ],
            ],

            Role::KITCHEN->value => [
                [
                    'id' => 'kds',
                    'title' => 'Pass',
                    'href' => '/kitchen',
                    'badge' => null,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
                [
                    'id' => 'waiter',
                    'title' => 'Order feed',
                    'href' => '/orders',
                    'badge' => null,
                    'capability' => Capability::SEE_ALL_ORDERS,
                ],
            ],

            default => [],
        };
    }
}
