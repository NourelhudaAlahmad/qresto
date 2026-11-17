<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Card and wallet orders used to stop at SERVED forever, because only the
 * floor's cash flow ever moved an order to PAID. Close the ones already
 * stuck there, with the same audit trail a live close writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('orders')
            ->where('status', 'served')
            ->where('is_paid', true)
            ->orderBy('id')
            ->chunkById(500, function ($orders) use ($now): void {
                $ids = $orders->pluck('id')->all();

                DB::table('orders')->whereIn('id', $ids)->update([
                    'status' => 'paid',
                    'updated_at' => $now,
                ]);

                DB::table('order_events')->insert(array_map(fn (int $id): array => [
                    'order_id' => $id,
                    'from_status' => 'served',
                    'to_status' => 'paid',
                    'actor_id' => null,
                    'actor_kind' => 'system',
                    'occurred_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $ids));
            });
    }

    public function down(): void
    {
        // Closing a settled order is not undone.
    }
};
