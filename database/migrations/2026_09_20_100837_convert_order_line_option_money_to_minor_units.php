<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('order_line_options')->update([
            'price_delta' => DB::raw('ROUND(price_delta * 100)'),
        ]);

        Schema::table('order_line_options', function (Blueprint $table) {
            $table->bigInteger('price_delta')
                ->default(0)
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('order_line_options')->update([
            'price_delta' => DB::raw('price_delta / 100'),
        ]);

        Schema::table('order_line_options', function (Blueprint $table) {
            $table->decimal('price_delta', 10, 2)
                ->default(0)
                ->change();
        });
    }
};
