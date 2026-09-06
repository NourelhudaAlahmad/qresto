<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\MenuItem;
use App\Models\User;

class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MenuItem $menuItem): bool
    {
        return $menuItem->restaurant_id === $user->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::EDIT_MENU_PRICES->value);
    }

    public function update(User $user, MenuItem $menuItem): bool
    {
        return $user->can(Capability::EDIT_MENU_PRICES->value)
            && $menuItem->restaurant_id === $user->restaurant_id;
    }

    public function delete(User $user, MenuItem $menuItem): bool
    {
        return $user->can(Capability::EDIT_MENU_PRICES->value)
            && $menuItem->restaurant_id === $user->restaurant_id;
    }

    public function restore(User $user, MenuItem $menuItem): bool
    {
        return $user->can(Capability::EDIT_MENU_PRICES->value)
            && $menuItem->restaurant_id === $user->restaurant_id;
    }

    public function forceDelete(User $user, MenuItem $menuItem): bool
    {
        return $user->can(Capability::EDIT_MENU_PRICES->value)
            && $menuItem->restaurant_id === $user->restaurant_id;
    }
}
