<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('order_lines')->update([
            'unit_price' => DB::raw('ROUND(unit_price * 100)'),
            'line_total' => DB::raw('ROUND(line_total * 100)'),
        ]);

        Schema::table('order_lines', function (Blueprint $table) {
            $table->bigInteger('unit_price')
                ->change();

            $table->bigInteger('line_total')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('order_lines')->update([
            'unit_price' => DB::raw('unit_price / 100'),
            'line_total' => DB::raw('line_total / 100'),
        ]);

        Schema::table('order_lines', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)
                ->change();

            $table->decimal('line_total', 10, 2)
                ->change();
        });
    }
};
