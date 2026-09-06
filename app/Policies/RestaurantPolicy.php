<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\Restaurant;
use App\Models\User;

class RestaurantPolicy
{
    public function view(User $user, Restaurant $restaurant): bool
    {
        return $user->restaurant_id === $restaurant->id;
    }

    public function update(User $user, Restaurant $restaurant): bool
    {
        return $user->can(Capability::MANAGE_RESTAURANT_CONFIG->value)
            && $user->restaurant_id === $restaurant->id;
    }

    public function delete(User $user, Restaurant $restaurant): bool
    {
        return $user->can(Capability::MANAGE_RESTAURANT_CONFIG->value)
            && $user->restaurant_id === $restaurant->id;
    }
}
