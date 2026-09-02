<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at');

            $table->timestamps();

            $table->index([
                'restaurant_id',
                'starts_at',
                'ends_at',
            ]);
        });

        Schema::create('table_user', function (Blueprint $table) {
            $table->foreignId('shift_id')
                ->constrained('shifts')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('table_id')
                ->constrained('tables')
                ->cascadeOnDelete();

            $table->primary([
                'shift_id',
                'user_id',
                'table_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_user');
        Schema::dropIfExists('shifts');
    }
};
