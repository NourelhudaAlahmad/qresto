<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();

            /*
             * The order this line belongs to.
             */
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            /*
             * Original menu item.
             *
             * Nullable because the menu item may be deleted later.
             * The snapshot fields below preserve the historical data.
             */
            $table->foreignId('menu_item_id')
                ->nullable()
                ->constrained('menu_items')
                ->nullOnDelete();

            /*
             * Historical snapshot of the menu item.
             *
             * These values must be copied when the order is placed.
             * Editing/deleting the MenuItem later must not change
             * the historical order.
             */
            $table->string('name_snapshot');

            $table->decimal('unit_price', 10, 2);

            /*
             * Ordered quantity.
             */
            $table->unsignedInteger('qty');

            /*
             * Total for this line:
             *
             * unit_price × qty + option price deltas
             *
             * The final calculation will be handled by OrderTotals.
             */
            $table->decimal('line_total', 10, 2);

            /*
             * Guest note.
             *
             * This travels with the line to waiter/kitchen projections.
             */
            $table->text('note')->nullable();

            /*
             * Kitchen/pass station.
             *
             * Examples:
             * grill, kitchen, bar, dessert
             */
            $table->string('station')->nullable();

            $table->timestamps();

            /*
             * Useful when loading all lines for an order.
             */
            $table->index('order_id');

            /*
             * Useful for menu-item history/reporting.
             */
            $table->index('menu_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
