<?php

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            /*
             * Restaurant that owns the order.
             */
            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();

            /*
             * Physical restaurant table.
             */
            $table->foreignId('table_id')
                ->nullable()
                ->constrained('tables')
                ->nullOnDelete();

            /*
             * Guest/table session this order belongs to.
             */
            $table->foreignId('table_session_id')
                ->nullable()
                ->constrained('table_sessions')
                ->nullOnDelete();

            /*
             * Short human-readable order code.
             * Unique within a restaurant, not globally.
             */
            $table->string('code');

            $table->string('guest_name')->nullable();

            /*
             * Staff member currently responsible for the order.
             */
            $table->foreignId('assigned_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            /*
             * Current status.
             *
             * Status changes must go through Order::transitionTo().
             */
            $table->string('status')
                ->default(OrderStatus::PLACED->value);

            $table->timestamp('placed_at')->nullable();

            /*
             * Monetary values are stored as decimals,
             * not floats, to avoid precision problems.
             */
            $table->decimal('subtotal', 10, 2)->default(0);

            $table->decimal('service_pct', 5, 2)->default(0);

            $table->decimal('service_amount', 10, 2)->default(0);

            $table->decimal('tip_amount', 10, 2)->default(0);

            $table->decimal('discount_amount', 10, 2)->default(0);

            $table->decimal('total', 10, 2)->default(0);

            $table->boolean('is_paid')->default(false);

            $table->timestamp('paid_at')->nullable();

            $table->text('table_note')->nullable();

            /*
             * Void/cancellation information.
             */
            $table->text('void_reason')->nullable();

            $table->foreignId('voided_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('voided_at')->nullable();

            $table->timestamps();

            /*
             * Order code is unique per restaurant.
             *
             * Example:
             * Restaurant A -> A-1043
             * Restaurant B -> A-1043
             *
             * Both are allowed because uniqueness is scoped
             * to the restaurant.
             */
            $table->unique([
                'restaurant_id',
                'code',
            ]);

            /*
             * Useful indexes for waiter screens and reports.
             */
            $table->index([
                'restaurant_id',
                'status',
            ]);

            $table->index([
                'restaurant_id',
                'is_paid',
            ]);

            $table->index('placed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
