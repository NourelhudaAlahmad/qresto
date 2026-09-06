<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permission_audits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('actor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('role');
            $table->string('permission');
            $table->boolean('granted');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['role', 'permission']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_audits');
    }
};
