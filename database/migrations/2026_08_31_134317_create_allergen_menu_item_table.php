<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('allergen_menu_item', function (Blueprint $table) {
            $table->foreignId('allergen_id')
                ->constrained('allergens')
                ->cascadeOnDelete();

            $table->foreignId('menu_item_id')
                ->constrained('menu_items')
                ->cascadeOnDelete();

            $table->boolean('may_contain')->default(false);

            $table->primary([
                'allergen_id',
                'menu_item_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('allergen_menu_item');
    }
};

