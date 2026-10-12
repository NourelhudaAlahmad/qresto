<?php

namespace App\Services\Auth;

use App\Models\Shift;
use App\Models\User;
use App\Notifications\StaffAccountLocked;

class NotifyOnShiftManager
{
    public function notify(User $lockedUser): void
    {
        if ($lockedUser->restaurant_id === null) {
            return;
        }

        $managerIds = Shift::query()
            ->where('restaurant_id', $lockedUser->restaurant_id)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->whereHas('user', function ($query) use ($lockedUser) {
                $query
                    ->where('restaurant_id', $lockedUser->restaurant_id)
                    ->where('is_active', true)
                    ->whereHas('roles', function ($roleQuery) {
                        $roleQuery->where('name', 'manager')->where('guard_name', 'web');
                    });
            })
            ->distinct()
            ->pluck('user_id');

        $managers = User::query()
            ->whereKey($managerIds)
            ->where('restaurant_id', $lockedUser->restaurant_id)
            ->where('is_active', true)
            ->role('manager')
            ->get();

        foreach ($managers as $manager) {
            $manager->notify(new StaffAccountLocked($lockedUser));
        }
    }
}
