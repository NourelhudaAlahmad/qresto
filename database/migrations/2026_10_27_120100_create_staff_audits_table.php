<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();

            // Who made the change. Kept if the actor is later removed.
            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Whose account changed.
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // created | updated | deactivated | reactivated | invited
            $table->string('action', 32);

            $table->json('changes')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['restaurant_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_audits');
    }
};
