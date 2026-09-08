<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderCodeGenerator
{
    public function generate(
        int $restaurantId,
        ?CarbonInterface $serviceDate = null,
    ): string {
        $date = ($serviceDate ?? now())->toDateString();

        $number = DB::transaction(function () use ($restaurantId, $date): int {
            $sequence = DB::table('order_sequences')
                ->where('restaurant_id', $restaurantId)
                ->where('service_date', $date)
                ->lockForUpdate()
                ->first();

            if ($sequence === null) {
                try {
                    DB::table('order_sequences')->insert([
                        'restaurant_id' => $restaurantId,
                        'service_date' => $date,
                        'next_number' => 1041,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return 1040;
                } catch (\Throwable) {
                    $sequence = DB::table('order_sequences')
                        ->where('restaurant_id', $restaurantId)
                        ->where('service_date', $date)
                        ->lockForUpdate()
                        ->first();

                    if ($sequence === null) {
                        throw new RuntimeException(
                            'Unable to create or lock the order sequence.'
                        );
                    }
                }
            }

            $number = (int) $sequence->next_number;

            DB::table('order_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'next_number' => $number + 1,
                    'updated_at' => now(),
                ]);

            return $number;
        });

        return '#A-' . $number;
    }
}