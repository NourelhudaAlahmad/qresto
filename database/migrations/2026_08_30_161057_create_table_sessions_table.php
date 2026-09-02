<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();

            $table->foreignId('restaurant_table_id')
                ->constrained('tables')
                ->cascadeOnDelete();

            $table->string('token')->unique();

            $table->string('guest_name')->nullable();

            $table->unsignedTinyInteger('party_size')->default(1);

            $table->timestamp('opened_at');

            $table->timestamp('closed_at')->nullable();

            $table->timestamp('last_seen_at')->nullable();

            $table->timestamps();

            $table->index([
                'restaurant_id',
                'restaurant_table_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_sessions');
    }
};
