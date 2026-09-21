<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();

            $table->string('code');

            $table->string('kind');

            $table->decimal('value', 10, 2);

            $table->timestamp('starts_at')->nullable();

            $table->timestamp('ends_at')->nullable();

            $table->unsignedInteger('max_uses')->nullable();

            $table->unsignedInteger('uses')->default(0);

            $table->timestamps();

            $table->unique([
                'restaurant_id',
                'code',
            ]);

            $table->index([
                'restaurant_id',
                'starts_at',
                'ends_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
