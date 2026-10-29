<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menu management (#31): dishes soft-delete so historic order lines keep
     * pointing at them, and each photo keeps its original next to a square
     * list thumbnail.
     */
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('photo_thumb_path')->nullable()->after('photo_path');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn('photo_thumb_path');
        });
    }
};
