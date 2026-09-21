<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cart_id')
                ->constrained('carts')
                ->cascadeOnDelete();

            $table->foreignId('menu_item_id')
                ->constrained('menu_items')
                ->cascadeOnDelete();

            $table->string('name_snapshot');

            // Stored in minor units.
            // Example: 19.50 TRY => 1950.
            $table->bigInteger('unit_price');

            $table->unsignedInteger('qty');

            // unit_price * qty.
            // Option deltas can be incorporated later when cart options are added.
            $table->bigInteger('line_total');

            $table->text('note')
                ->nullable();

            $table->timestamps();

            $table->index('cart_id');
            $table->index('menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_lines');
    }
};
