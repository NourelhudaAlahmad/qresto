<?php

namespace App\Services;

use App\Enums\Capability;
use App\Models\PermissionAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as SpatieRole;
use Spatie\Permission\PermissionRegistrar;

class PermissionManager
{
    /**
     * Grant a capability to a role and write an audit record.
     */
    public function grant(
        User $actor,
        SpatieRole $role,
        Capability $capability,
    ): void {
        DB::transaction(function () use ($actor, $role, $capability): void {
            $role->givePermissionTo($capability->value);

            PermissionAudit::create([
                'actor_id' => $actor->id,
                'role' => $role->name,
                'permission' => $capability->value,
                'granted' => true,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Revoke a capability from a role and write an audit record.
     */
    public function revoke(
        User $actor,
        SpatieRole $role,
        Capability $capability,
    ): void {
        DB::transaction(function () use ($actor, $role, $capability): void {
            $role->revokePermissionTo($capability->value);

            PermissionAudit::create([
                'actor_id' => $actor->id,
                'role' => $role->name,
                'permission' => $capability->value,
                'granted' => false,
            ]);
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
