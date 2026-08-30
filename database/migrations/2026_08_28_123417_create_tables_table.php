<?php

use App\Enums\TableState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();

            $table->string('number');

            $table->unsignedTinyInteger('seats');

            $table->string('state')->default(TableState::FREE->value);

            $table->unsignedTinyInteger('party_size')->default(0);

            $table->timestamp('seated_at')->nullable();

            $table->string('qr_token')->unique();

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->unique(['restaurant_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
