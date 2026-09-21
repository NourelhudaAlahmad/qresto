<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_sequences', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();

            $table->date('service_date');

            $table->unsignedInteger('next_number')
                ->default(1040);

            $table->timestamps();

            $table->unique([
                'restaurant_id',
                'service_date',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_sequences');
    }
};
