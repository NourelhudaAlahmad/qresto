<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A deleted category keeps its row, so the database never cascades the
 * delete into its (soft-deleted) dishes and their sales history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
