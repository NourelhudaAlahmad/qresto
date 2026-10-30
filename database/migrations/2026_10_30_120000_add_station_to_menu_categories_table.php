<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kitchen station a category's dishes are cooked at (#33): grill, cold
     * or sweet. Null for things the kitchen does not make, such as drinks.
     */
    public function up(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->string('station', 16)->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('menu_categories', function (Blueprint $table) {
            $table->dropColumn('station');
        });
    }
};
