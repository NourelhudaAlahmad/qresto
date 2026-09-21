<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\RestaurantTable;
use App\Models\User;

class RestaurantTablePolicy
{
    /**
     * Determine whether the user can view any tables.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Capability::SEE_OWN_TABLES->value)
            || $user->can(Capability::SEE_ALL_ORDERS->value);
    }

    /**
     * Determine whether the user can view the table.
     */
    public function view(User $user, RestaurantTable $restaurantTable): bool
    {
        if ($user->can(Capability::SEE_ALL_ORDERS->value)) {
            return true;
        }

        return $user->can(Capability::SEE_OWN_TABLES->value)
            && $restaurantTable->restaurant_id === $user->restaurant_id;
    }

    /**
     * Determine whether the user can create a table.
     */
    public function create(User $user): bool
    {
        return $user->can(Capability::MANAGE_RESTAURANT_CONFIG->value);
    }

    /**
     * Determine whether the user can update the table.
     */
    public function update(User $user, RestaurantTable $restaurantTable): bool
    {
        return $user->can(Capability::MANAGE_RESTAURANT_CONFIG->value)
            && $restaurantTable->restaurant_id === $user->restaurant_id;
    }

    /**
     * Determine whether the user can delete the table.
     */
    public function delete(User $user, RestaurantTable $restaurantTable): bool
    {
        return $user->can(Capability::MANAGE_RESTAURANT_CONFIG->value)
            && $restaurantTable->restaurant_id === $user->restaurant_id;
    }

    /**
     * Determine whether the user can restore the table.
     */
    public function restore(User $user, RestaurantTable $restaurantTable): bool
    {
        return $user->can(Capability::MANAGE_RESTAURANT_CONFIG->value)
            && $restaurantTable->restaurant_id === $user->restaurant_id;
    }

    /**
     * Determine whether the user can permanently delete the table.
     */
    public function forceDelete(User $user, RestaurantTable $restaurantTable): bool
    {
        return $user->can(Capability::MANAGE_RESTAURANT_CONFIG->value)
            && $restaurantTable->restaurant_id === $user->restaurant_id;
    }
}
