<?php

namespace App\Actions\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Add, rename, retire and reissue tables (#29).
 */
class ManageTables
{
    /**
     * @param  array{number: string, seats: int, sort_order?: int|null}  $data
     */
    public function add(User $admin, array $data): RestaurantTable
    {
        return DB::transaction(function () use ($admin, $data): RestaurantTable {
            $retired = RestaurantTable::withoutGlobalScope(RestaurantTable::ACTIVE_SCOPE)
                ->where('restaurant_id', $admin->restaurant_id)
                ->where('number', $data['number'])
                ->whereNotNull('retired_at')
                ->first();

            $values = [
                'seats' => $data['seats'],
                'sort_order' => $data['sort_order'] ?? $this->nextSortOrder((int) $admin->restaurant_id),
                // Always a new sticker: an old, retired one stays dead.
                'qr_token' => RestaurantTable::newQrToken(),
                'retired_at' => null,
            ];

            if ($retired !== null) {
                $retired->forceFill($values)->save();

                return $retired;
            }

            return RestaurantTable::query()->create([
                ...$values,
                'restaurant_id' => $admin->restaurant_id,
                'number' => $data['number'],
            ]);
        });
    }

    /**
     * @param  array{number: string, seats: int, sort_order?: int|null}  $data
     */
    public function update(RestaurantTable $table, array $data): RestaurantTable
    {
        $table->forceFill([
            'number' => $data['number'],
            'seats' => $data['seats'],
            'sort_order' => $data['sort_order'] ?? $table->sort_order,
        ])->save();

        return $table;
    }

    /**
     * Refused while guests are at the table or a bill is unpaid. The row
     * stays, so its orders keep pointing at it; its sticker stops working.
     */
    public function retire(RestaurantTable $table): void
    {
        DB::transaction(function () use ($table): void {
            $hasOpenSession = TableSession::withoutGlobalScopes()
                ->where('restaurant_table_id', $table->id)
                ->whereNull('closed_at')
                ->get()
                ->contains(fn (TableSession $session): bool => $session->isActive());

            if ($hasOpenSession) {
                throw ValidationException::withMessages([
                    'table' => "Table {$table->number} has guests right now. Free it first.",
                ]);
            }

            $unpaid = Order::query()
                ->where('table_id', $table->id)
                ->where('is_paid', false)
                ->whereNotIn('status', [OrderStatus::CANCELLED->value, OrderStatus::PAID->value])
                ->exists();

            if ($unpaid) {
                throw ValidationException::withMessages([
                    'table' => "Table {$table->number} still has an unpaid bill.",
                ]);
            }

            $table->forceFill(['retired_at' => now()])->save();
        });
    }

    /**
     * New sticker for this table. The old token stops working on the very
     * next scan, and any session opened with it is closed, so a
     * photographed sticker is not a permanent open door.
     */
    public function reissue(RestaurantTable $table): RestaurantTable
    {
        return DB::transaction(function () use ($table): RestaurantTable {
            $table->forceFill(['qr_token' => RestaurantTable::newQrToken()])->save();

            TableSession::withoutGlobalScopes()
                ->where('restaurant_table_id', $table->id)
                ->whereNull('closed_at')
                ->update(['closed_at' => now()]);

            return $table;
        });
    }

    private function nextSortOrder(int $restaurantId): int
    {
        return (int) RestaurantTable::withoutGlobalScope(RestaurantTable::ACTIVE_SCOPE)
            ->where('restaurant_id', $restaurantId)
            ->max('sort_order') + 1;
    }
}
