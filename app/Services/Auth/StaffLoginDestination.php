<?php

namespace App\Services\Auth;

use App\Enums\Capability;
use App\Models\User;
use Illuminate\Support\Facades\Route;

class StaffLoginDestination
{
    /**
     * Where each role lands after signing in: its main working screen.
     *
     * Each role lists its preferred screen first, then fallbacks. The first
     * route that exists and whose capability the user holds wins, so nobody
     * lands on a placeholder or a 403, even after the matrix changes (#28).
     *
     * @var array<string, list<array{0: string, 1: Capability}>>
     */
    private const DESTINATIONS = [
        'waiter' => [
            ['app.my-orders', Capability::SEE_OWN_TABLES],
        ],
        // The pass display (#33), then the order feed.
        'kitchen' => [
            ['app.pass', Capability::CHANGE_ORDER_STATUS],
            ['app.orders', Capability::SEE_ALL_ORDERS],
        ],
        'manager' => [
            ['manager.overview', Capability::MANAGE_WAITER_ACCOUNTS],
            ['app.orders', Capability::SEE_ALL_ORDERS],
        ],
        'admin' => [
            ['app.system', Capability::MANAGE_RESTAURANT_CONFIG],
            ['manager.overview', Capability::MANAGE_WAITER_ACCOUNTS],
        ],
    ];

    public function for(User $user): string
    {
        foreach (self::DESTINATIONS as $role => $candidates) {
            if (! $user->hasRole($role)) {
                continue;
            }

            foreach ($candidates as [$routeName, $capability]) {
                if (Route::has($routeName) && $user->can($capability->value)) {
                    return route($routeName, absolute: false);
                }
            }
        }

        return route('dashboard', absolute: false);
    }
}
