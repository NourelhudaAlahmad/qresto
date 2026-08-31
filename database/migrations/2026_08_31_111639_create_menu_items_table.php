<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('menu_category_id')
                ->constrained('menu_categories')
                ->cascadeOnDelete();

            $table->string('name');
            $table->text('description')->nullable();

            $table->json('translations')->nullable();

            // Money stored as minor units (e.g. 1250 = 12.50 TRY)
            $table->unsignedInteger('price');

            $table->string('photo_path')->nullable();

            $table->unsignedSmallInteger('prep_minutes')->nullable();

            $table->boolean('is_available')->default(true);
            $table->boolean('is_scheduled')->default(false);

            $table->dateTime('available_from')->nullable();
            $table->dateTime('available_until')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->json('dietary_tags')->nullable();

            $table->boolean('chef_flag')->default(false);

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index([
                'restaurant_id',
                'menu_category_id',
                'sort_order',
            ]);

            $table->index([
                'restaurant_id',
                'is_available',
                'is_scheduled',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};