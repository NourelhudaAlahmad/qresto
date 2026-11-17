<?php

namespace App\Services;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\PermissionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Changes one cell of the actor's own restaurant's grid and audits it.
 * Other restaurants, and the defaults, are never touched.
 */
class PermissionManager
{
    public function __construct(
        private readonly RestaurantCapabilities $capabilities,
    ) {}

    /**
     * Grant a capability to a role and write an audit record.
     */
    public function grant(User $actor, Role $role, Capability $capability): void
    {
        $this->change($actor, $role, $capability, true);
    }

    /**
     * Revoke a capability from a role and write an audit record.
     */
    public function revoke(User $actor, Role $role, Capability $capability): void
    {
        $this->change($actor, $role, $capability, false);
    }

    private function change(User $actor, Role $role, Capability $capability, bool $granted): void
    {
        $restaurantId = $actor->restaurant_id
            ?? throw new LogicException('Only a restaurant\'s own staff can change its permissions.');

        DB::transaction(function () use ($actor, $restaurantId, $role, $capability, $granted): void {
            $this->capabilities->set($restaurantId, $role->value, $capability->value, $granted);

            PermissionAudit::create([
                'restaurant_id' => $restaurantId,
                'actor_id' => $actor->id,
                'role' => $role->value,
                'permission' => $capability->value,
                'granted' => $granted,
            ]);
        });
    }
}
