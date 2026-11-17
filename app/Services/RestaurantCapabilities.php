<?php

namespace App\Services;

use App\Models\RestaurantRoleCapability;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Each restaurant's role → capability grid.
 *
 * The global role permissions that RolesSeeder writes are every
 * restaurant's defaults. A restaurant's admin changes its own grid only:
 * each change is a RestaurantRoleCapability row that grants or revokes one
 * capability for one role in that restaurant, and a cell set back to its
 * default drops the row, so later changes to the defaults still reach it.
 *
 * User::hasPermissionViaRole() reads through here, so can(), the can:
 * middleware and the capability snapshot all follow the user's restaurant.
 */
class RestaurantCapabilities
{
    /**
     * @var array<int, array<string, array<string, bool>>> restaurant → role → capability → granted
     */
    private array $overrides = [];

    /**
     * The capabilities a user holds through their roles, in their
     * restaurant. A user with no restaurant gets the defaults.
     *
     * @return list<string>
     */
    public function forUser(User $user): array
    {
        $capabilities = [];

        foreach ($user->loadMissing('roles.permissions')->roles as $role) {
            if (! $role instanceof SpatieRole) {
                continue;
            }

            array_push($capabilities, ...$this->apply($user->restaurant_id, (string) $role->name, $this->defaultsOf($role)));
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * Every role's capabilities in one restaurant, for the matrix.
     *
     * @return array<string, list<string>>
     */
    public function grid(int $restaurantId): array
    {
        $grid = [];

        $roles = SpatieRole::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->get();

        foreach ($roles as $role) {
            $grid[(string) $role->name] = $this->apply($restaurantId, (string) $role->name, $this->defaultsOf($role));
        }

        return $grid;
    }

    public function roleHas(int $restaurantId, string $role, string $capability): bool
    {
        return in_array($capability, $this->grid($restaurantId)[$role] ?? [], true);
    }

    /**
     * Grant or revoke one cell for one restaurant.
     */
    public function set(int $restaurantId, string $role, string $capability, bool $granted): void
    {
        $default = SpatieRole::query()
            ->where('guard_name', 'web')
            ->where('name', $role)
            ->first()
            ?->hasPermissionTo($capability) ?? false;

        $cell = RestaurantRoleCapability::query()->withoutGlobalScope('restaurant')
            ->where('restaurant_id', $restaurantId)
            ->where('role', $role)
            ->where('capability', $capability);

        if ($granted === $default) {
            $cell->delete();
        } else {
            RestaurantRoleCapability::query()->withoutGlobalScope('restaurant')->updateOrCreate(
                ['restaurant_id' => $restaurantId, 'role' => $role, 'capability' => $capability],
                ['granted' => $granted],
            );
        }

        unset($this->overrides[$restaurantId]);
    }

    /**
     * The capabilities a role holds by default, in every restaurant.
     *
     * @return list<string>
     */
    private function defaultsOf(SpatieRole $role): array
    {
        return array_values($role->permissions
            ->map(fn (Model $permission): string => (string) $permission->getAttribute('name'))
            ->all());
    }

    /**
     * @param  list<string>  $defaults
     * @return list<string>
     */
    private function apply(?int $restaurantId, string $role, array $defaults): array
    {
        if ($restaurantId === null) {
            return $defaults;
        }

        $capabilities = array_fill_keys($defaults, true);

        foreach ($this->overrides($restaurantId)[$role] ?? [] as $capability => $granted) {
            if ($granted) {
                $capabilities[$capability] = true;
            } else {
                unset($capabilities[$capability]);
            }
        }

        return array_map('strval', array_keys($capabilities));
    }

    /**
     * @return array<string, array<string, bool>>
     */
    private function overrides(int $restaurantId): array
    {
        if (! isset($this->overrides[$restaurantId])) {
            $this->overrides[$restaurantId] = [];

            $rows = RestaurantRoleCapability::query()->withoutGlobalScope('restaurant')
                ->where('restaurant_id', $restaurantId)
                ->get(['role', 'capability', 'granted']);

            foreach ($rows as $row) {
                $this->overrides[$restaurantId][(string) $row->role][(string) $row->capability] = (bool) $row->granted;
            }
        }

        return $this->overrides[$restaurantId];
    }
}
