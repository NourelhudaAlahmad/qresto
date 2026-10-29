<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every CSV or PDF export is recorded with who made it and when (#32).
     */
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained('restaurants')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('format', 8); // csv | pdf
            $table->string('preset', 16)->nullable();
            $table->date('range_start');
            $table->date('range_end');
            $table->unsignedInteger('row_count')->default(0);
            $table->timestamp('generated_at');

            $table->index(['restaurant_id', 'generated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
