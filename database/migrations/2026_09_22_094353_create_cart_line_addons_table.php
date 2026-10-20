<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_line_addons', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_line_id')
                ->constrained('cart_lines')
                ->cascadeOnDelete();

            $table->foreignId('menu_item_addon_id')
                ->nullable()
                ->constrained('menu_item_addons')
                ->nullOnDelete();

            $table->string('label_snapshot');

            // Stored in minor units.
            // Example: 2.00 TRY => 200.
            $table->bigInteger('price_delta');

            $table->timestamps();

            $table->index('cart_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_line_addons');
    }
};
