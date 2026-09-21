<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Add the order currency.
         * All monetary values in an order use this currency.
         */
        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)
                ->default('TRY')
                ->after('placed_at');
        });

        /*
         * Convert existing decimal amounts to minor units.
         *
         * Example:
         * 19.50 -> 1950
         * 2.50  -> 250
         */
        DB::table('orders')->update([
            'subtotal' => DB::raw('ROUND(subtotal * 100)'),
            'service_amount' => DB::raw('ROUND(service_amount * 100)'),
            'tip_amount' => DB::raw('ROUND(tip_amount * 100)'),
            'discount_amount' => DB::raw('ROUND(discount_amount * 100)'),
            'total' => DB::raw('ROUND(total * 100)'),
        ]);

        /*
         * Store monetary values as integers.
         */
        Schema::table('orders', function (Blueprint $table) {
            $table->bigInteger('subtotal')->default(0)->change();
            $table->bigInteger('service_amount')->default(0)->change();
            $table->bigInteger('tip_amount')->default(0)->change();
            $table->bigInteger('discount_amount')->default(0)->change();
            $table->bigInteger('total')->default(0)->change();
        });
    }

    public function down(): void
    {
        /*
         * Convert minor units back to decimal values.
         */
        DB::table('orders')->update([
            'subtotal' => DB::raw('subtotal / 100'),
            'service_amount' => DB::raw('service_amount / 100'),
            'tip_amount' => DB::raw('tip_amount / 100'),
            'discount_amount' => DB::raw('discount_amount / 100'),
            'total' => DB::raw('total / 100'),
        ]);

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('subtotal', 10, 2)
                ->default(0)
                ->change();

            $table->decimal('service_amount', 10, 2)
                ->default(0)
                ->change();

            $table->decimal('tip_amount', 10, 2)
                ->default(0)
                ->change();

            $table->decimal('discount_amount', 10, 2)
                ->default(0)
                ->change();

            $table->decimal('total', 10, 2)
                ->default(0)
                ->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }
};
