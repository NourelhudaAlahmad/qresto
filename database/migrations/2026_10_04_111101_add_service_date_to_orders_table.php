<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->date('service_date')->nullable();
        });

        DB::table('orders')
            ->select([
                'id',
                'placed_at',
                'created_at',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $timestamp = $order->placed_at ?? $order->created_at;

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'service_date' => Carbon::parse($timestamp)
                                ->toDateString(),
                        ]);
                }
            });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique([
                'restaurant_id',
                'code',
            ]);

            $table->unique([
                'restaurant_id',
                'service_date',
                'code',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique([
                'restaurant_id',
                'service_date',
                'code',
            ]);

            $table->dropColumn('service_date');

            $table->unique([
                'restaurant_id',
                'code',
            ]);
        });
    }
};
