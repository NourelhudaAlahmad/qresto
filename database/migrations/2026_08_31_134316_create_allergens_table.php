<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('name');

            $table->json('translations')->nullable();

            $table->timestamps();

            $table->index([
                'restaurant_id',
                'name',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergens');
    }
};

