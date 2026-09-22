<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->foreignId('menu_item_variant_id')
                ->nullable()
                ->after('menu_item_id')
                ->constrained('menu_item_variants')
                ->nullOnDelete();

            $table->string('variant_label_snapshot')
                ->nullable()
                ->after('name_snapshot');

            $table->bigInteger('variant_price_delta')
                ->default(0)
                ->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->dropForeign(['menu_item_variant_id']);

            $table->dropColumn([
                'menu_item_variant_id',
                'variant_label_snapshot',
                'variant_price_delta',
            ]);
        });
    }
};
