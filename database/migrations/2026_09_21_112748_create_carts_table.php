<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();

            $table->foreignId('table_session_id')
                ->constrained('table_sessions')
                ->cascadeOnDelete();

            $table->string('currency', 3);

            $table->timestamps();

            $table->unique('table_session_id');

            $table->index([
                'restaurant_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
