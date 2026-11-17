<?php

namespace App\Actions\Orders;

use App\Enums\TableState;
use App\Models\Cart;
use App\Models\Order;
use App\Models\RestaurantTable;
use Illuminate\Support\Facades\DB;

/**
 * Once an order is placed (paid, deferred, or confirmed after 3-D Secure,
 * in the browser or by the gateway's webhook), found by the service
 * journey (#37):
 *
 * - its cart is emptied, so the guest's next visit starts fresh and a
 *   second checkout cannot order the same dishes again;
 * - its table shows as ordered on the floor, so a table with guests and
 *   an open bill never looks free.
 *
 * A failed payment never gets here: its cart stays for another try.
 * Running it twice changes nothing the second time.
 */
final class FinishPlacedOrder
{
    public function handle(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $cart = Cart::query()
                ->where('table_session_id', $order->table_session_id)
                ->first();

            if ($cart !== null) {
                $cart->lines()->each(function ($line): void {
                    $line->addons()->delete();
                    $line->delete();
                });

                $cart->forceFill(['note' => null])->save();
            }

            $table = $order->table_id === null
                ? null
                : RestaurantTable::query()->withoutGlobalScopes()->find($order->table_id);

            if ($table !== null && in_array($table->state, [TableState::FREE, TableState::SEATED], true)) {
                $table->forceFill([
                    'state' => TableState::ORDERED,
                    'seated_at' => $table->seated_at ?? now(),
                ])->save();
            }
        });
    }
}
