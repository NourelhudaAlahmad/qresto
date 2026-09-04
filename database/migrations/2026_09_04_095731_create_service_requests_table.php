<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('table_id')
                ->constrained('tables')
                ->cascadeOnDelete();

            $table->foreignId('table_session_id')
                ->nullable()
                ->constrained('table_sessions')
                ->nullOnDelete();

            $table->string('kind');

            $table->string('status');

            $table->timestamp('requested_at');

            $table->foreignId('acknowledged_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('acknowledged_at')->nullable();

            $table->timestamps();

            $table->index([
                'table_id',
                'status',
            ]);

            $table->index([
                'table_session_id',
                'status',
            ]);

            $table->index('requested_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_requests');
    }
};
