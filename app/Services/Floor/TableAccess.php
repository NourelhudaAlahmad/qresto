<?php

namespace App\Services\Floor;

use App\Enums\Capability;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Who may act on a table.
 *
 * Anyone who sees all orders (manager, admin) may act on every table in
 * their restaurant. Someone who only sees their own tables (a waiter) may
 * act on the tables assigned to them on tonight's live shift, and no
 * others: "My tables" means exactly that.
 */
class TableAccess
{
    public function seesWholeFloor(User $user): bool
    {
        return $user->can(Capability::SEE_ALL_ORDERS->value);
    }

    /**
     * @return list<int>
     */
    public function assignedTableIds(User $user): array
    {
        $now = now();

        return array_values(DB::table('table_user')
            ->join('shifts', 'shifts.id', '=', 'table_user.shift_id')
            ->where('table_user.user_id', $user->id)
            ->where('shifts.starts_at', '<=', $now)
            ->where('shifts.ends_at', '>=', $now)
            ->pluck('table_user.table_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all());
    }

    public function sameRestaurant(User $user, RestaurantTable $table): bool
    {
        return $user->restaurant_id !== null
            && (int) $user->restaurant_id === (int) $table->restaurant_id;
    }

    public function canAct(User $user, RestaurantTable $table): bool
    {
        if (! $this->sameRestaurant($user, $table)) {
            return false;
        }

        return $this->seesWholeFloor($user)
            || in_array((int) $table->id, $this->assignedTableIds($user), true);
    }

    /**
     * 403 for another restaurant's table (as before) and for a table that
     * belongs to someone else tonight.
     */
    public function authorize(?User $user, RestaurantTable $table): void
    {
        abort_unless($user !== null && $this->sameRestaurant($user, $table), 403);

        abort_unless(
            $this->canAct($user, $table),
            403,
            'This table is not yours tonight.',
        );
    }
}
