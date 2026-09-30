<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('split_ways')
                ->default(1)
                ->after('discount_amount');

            $table->unsignedBigInteger('share_amount')
                ->nullable()
                ->after('split_ways');

            $table->string('idempotency_key', 100)
                ->nullable()
                ->after('share_amount');

            $table->unique(
                ['restaurant_id', 'idempotency_key'],
                'orders_restaurant_idempotency_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique('orders_restaurant_idempotency_unique');

            $table->dropColumn([
                'split_ways',
                'share_amount',
                'idempotency_key',
            ]);
        });
    }
};
