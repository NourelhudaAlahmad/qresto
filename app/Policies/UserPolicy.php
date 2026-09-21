<?php

namespace App\Policies;

use App\Enums\Capability;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Capability::MANAGE_WAITER_ACCOUNTS->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Capability::MANAGE_WAITER_ACCOUNTS->value)
            && $model->restaurant_id === $user->restaurant_id;
    }

    public function create(User $user): bool
    {
        return $user->can(Capability::MANAGE_WAITER_ACCOUNTS->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Capability::MANAGE_WAITER_ACCOUNTS->value)
            && $model->restaurant_id === $user->restaurant_id;
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can(Capability::MANAGE_WAITER_ACCOUNTS->value)
            && $model->restaurant_id === $user->restaurant_id;
    }

    public function restore(User $user, User $model): bool
    {
        return $user->can(Capability::MANAGE_WAITER_ACCOUNTS->value)
            && $model->restaurant_id === $user->restaurant_id;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return $user->can(Capability::MANAGE_WAITER_ACCOUNTS->value)
            && $model->restaurant_id === $user->restaurant_id;
    }
}
