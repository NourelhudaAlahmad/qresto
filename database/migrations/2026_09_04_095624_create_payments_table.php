<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            $table->string('method');

            $table->string('status');

            $table->decimal('amount', 10, 2);

            $table->decimal('tip_amount', 10, 2)->default(0);

            $table->string('gateway')->nullable();

            $table->string('gateway_intent_id')->nullable();

            $table->string('gateway_status')->nullable();

            $table->boolean('requires_3ds')->default(false);

            $table->text('failure_reason')->nullable();

            $table->foreignId('taken_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('paid_at')->nullable();

            $table->decimal('refunded_amount', 10, 2)->default(0);

            $table->timestamp('refunded_at')->nullable();

            $table->timestamps();

            $table->index([
                'order_id',
                'status',
            ]);

            $table->index('gateway_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
