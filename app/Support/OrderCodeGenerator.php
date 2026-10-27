<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class OrderCodeGenerator
{
    private const FIRST_NUMBER = 1040;

    private const PREFIX = '#A-';

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

            // Never hand out a code that already exists for this service
            // day. Orders can be written without going through here (the
            // demo seeder, imports), and the unique index on
            // (restaurant_id, service_date, code) would turn a repeat into
            // a 500 at checkout.
            $floor = max(
                self::FIRST_NUMBER,
                $this->highestExistingNumber($restaurantId, $date) + 1,
            );

            if ($sequence === null) {
                try {
                    DB::table('order_sequences')->insert([
                        'restaurant_id' => $restaurantId,
                        'service_date' => $date,
                        'next_number' => $floor + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    return $floor;
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

            $number = max((int) $sequence->next_number, $floor);

            DB::table('order_sequences')
                ->where('id', $sequence->id)
                ->update([
                    'next_number' => $number + 1,
                    'updated_at' => now(),
                ]);

            return $number;
        });

        return self::PREFIX.$number;
    }

    /**
     * Highest "#A-<n>" already used by this restaurant on this service day,
     * or 0 when there is none.
     */
    private function highestExistingNumber(int $restaurantId, string $date): int
    {
        $codes = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->whereDate('service_date', $date)
            ->where('code', 'like', self::PREFIX.'%')
            ->pluck('code');

        $highest = 0;

        foreach ($codes as $code) {
            $suffix = substr((string) $code, strlen(self::PREFIX));

            if (ctype_digit($suffix)) {
                $highest = max($highest, (int) $suffix);
            }
        }

        return $highest;
    }
}
