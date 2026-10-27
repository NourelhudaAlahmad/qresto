<?php

namespace App\Services\Admin;

use App\Enums\Capability;
use App\Enums\Role;
use App\Models\PermissionAudit;
use App\Models\User;
use App\Services\PermissionManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * The editable role → capability grid on the super admin dashboard (#28).
 *
 * Two guard rails keep the screen from locking everyone out:
 * the admin role always keeps "Restaurant & system config", and nobody
 * can revoke a capability from a role they hold themselves.
 */
class PermissionMatrix
{
    /**
     * Least to most privileged. Waiter, kitchen, manager and admin follow
     * the design's MATRIX column order; runner and cashier sit with waiter.
     */
    public const ROLE_ORDER = [
        Role::WAITER,
        Role::RUNNER,
        Role::CASHIER,
        Role::KITCHEN,
        Role::MANAGER,
        Role::ADMIN,
    ];

    public function __construct(
        private readonly PermissionManager $permissions,
    ) {}

    /**
     * @return array{
     *     roles: list<array{key: string, label: string}>,
     *     rows: list<array{
     *         capability: string,
     *         label: string,
     *         cells: array<string, array{granted: bool, locked: bool, reason: string|null}>
     *     }>
     * }
     */
    public function for(User $actor): array
    {
        $grants = $this->grants();

        $roles = array_map(
            fn (Role $role): array => [
                'key' => $role->value,
                'label' => ucfirst($role->value),
            ],
            self::ROLE_ORDER,
        );

        $rows = array_map(function (Capability $capability) use ($actor, $grants): array {
            $cells = [];

            foreach (self::ROLE_ORDER as $role) {
                $granted = in_array($capability->value, $grants[$role->value] ?? [], true);
                $reason = $granted ? $this->revokeBlockedReason($actor, $role, $capability) : null;

                $cells[$role->value] = [
                    'granted' => $granted,
                    'locked' => $reason !== null,
                    'reason' => $reason,
                ];
            }

            return [
                'capability' => $capability->value,
                'label' => $capability->label(),
                'cells' => $cells,
            ];
        }, Capability::cases());

        return ['roles' => $roles, 'rows' => $rows];
    }

    /**
     * Grant or revoke one cell. Returns false when nothing changed, so a
     * repeated click never writes a second audit row.
     */
    public function set(User $actor, Role $role, Capability $capability, bool $granted): bool
    {
        return DB::transaction(function () use ($actor, $role, $capability, $granted): bool {
            /** @var SpatieRole $spatieRole */
            $spatieRole = SpatieRole::findByName($role->value, 'web');

            if ($spatieRole->hasPermissionTo($capability->value) === $granted) {
                return false;
            }

            if (! $granted) {
                $reason = $this->revokeBlockedReason($actor, $role, $capability);

                if ($reason !== null) {
                    throw ValidationException::withMessages(['capability' => $reason]);
                }

                $this->permissions->revoke($actor, $spatieRole, $capability);

                return true;
            }

            $this->permissions->grant($actor, $spatieRole, $capability);

            return true;
        });
    }

    /**
     * @return list<array{id: int, actor: string, role: string, capability: string, granted: bool, at: string}>
     */
    public function recentAudits(int $limit = 10): array
    {
        return array_values(PermissionAudit::query()
            ->with('actor:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (PermissionAudit $audit): array => [
                'id' => (int) $audit->id,
                'actor' => $audit->actor->name ?? 'System',
                'role' => ucfirst((string) $audit->role),
                'capability' => Capability::tryFrom((string) $audit->permission)?->label()
                    ?? (string) $audit->permission,
                'granted' => (bool) $audit->granted,
                'at' => ($audit->created_at ?? now())->toIso8601String(),
            ])
            ->all());
    }

    public function revokeBlockedReason(User $actor, Role $role, Capability $capability): ?string
    {
        if ($role === Role::ADMIN && $capability === Capability::MANAGE_RESTAURANT_CONFIG) {
            return 'The admin role always keeps system config, so nobody is locked out.';
        }

        if ($actor->hasRole($role->value)) {
            return 'You cannot remove a capability from your own role.';
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function grants(): array
    {
        $grants = [];

        $roles = SpatieRole::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->get();

        foreach ($roles as $role) {
            $grants[(string) $role->name] = array_values(
                $role->permissions->pluck('name')->map(fn ($name): string => (string) $name)->all(),
            );
        }

        return $grants;
    }
}
