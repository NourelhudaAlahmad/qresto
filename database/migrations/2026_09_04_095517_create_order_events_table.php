<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('from_status')->nullable();

            $table->string('to_status');

            $table->foreignId('actor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('actor_kind');

            $table->timestamp('occurred_at');

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index([
                'order_id',
                'occurred_at',
            ]);

            $table->index([
                'actor_kind',
                'occurred_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_events');
    }
};
