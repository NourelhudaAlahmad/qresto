<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retiring a table is a soft state: its order history stays attached,
     * and it disappears from the floor, the QR sheet and the table picker.
     */
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $table->dropColumn('retired_at');
        });
    }
};
