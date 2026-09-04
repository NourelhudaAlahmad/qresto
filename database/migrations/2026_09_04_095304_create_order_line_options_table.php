<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_line_options', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_line_id')
                ->constrained('order_lines')
                ->cascadeOnDelete();

            $table->string('kind');
            $table->string('label_snapshot');
            $table->decimal('price_delta', 10, 2)->default(0);

            $table->timestamps();

            $table->index(['order_line_id', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_line_options');
    }
};
