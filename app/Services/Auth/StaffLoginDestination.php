<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Route;

class StaffLoginDestination
{
    public function for(User $user): string
    {
        $destinations = [
            'waiter' => 'waiter.orders',
            'kitchen' => 'kitchen.pass',
            'manager' => 'manager.overview',
            'admin' => 'admin.system',
        ];

        foreach ($destinations as $role => $routeName) {
            if ($user->hasRole($role)) {
                return Route::has($routeName)
                    ? route($routeName, absolute: false)
                    : route('dashboard', absolute: false);
            }
        }

        return route('dashboard', absolute: false);
    }
}
