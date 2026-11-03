<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * #49: restaurants.currency had no default and orders.currency defaulted to
 * TRY, which nothing in the product uses. Both now default to the configured
 * currency (config/qresto.php), the same value Money falls back to.
 */
return new class extends Migration
{
    public function up(): void
    {
        $currency = strtoupper((string) config('qresto.currency'));

        Schema::table('restaurants', function (Blueprint $table) use ($currency) {
            $table->string('currency', 3)->default($currency)->change();
        });

        Schema::table('orders', function (Blueprint $table) use ($currency) {
            $table->string('currency', 3)->default($currency)->change();
        });
    }

    public function down(): void
    {
        Schema::table('restaurants', function (Blueprint $table) {
            $table->string('currency', 3)->default(null)->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('currency', 3)->default('TRY')->change();
        });
    }
};
