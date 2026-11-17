<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Permission changes belong to the restaurant whose grid was edited, so
 * one restaurant's audit list never shows another restaurant's admins.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('permission_audits', function (Blueprint $table) {
            $table->foreignId('restaurant_id')
                ->nullable()
                ->after('id')
                ->constrained()
                ->cascadeOnDelete();

            $table->index(['restaurant_id', 'created_at']);
        });

        // Earlier rows belong to the actor's restaurant.
        DB::table('permission_audits')
            ->whereNull('restaurant_id')
            ->update([
                'restaurant_id' => DB::table('users')
                    ->select('restaurant_id')
                    ->whereColumn('users.id', 'permission_audits.actor_id'),
            ]);
    }

    public function down(): void
    {
        Schema::table('permission_audits', function (Blueprint $table) {
            $table->dropIndex(['restaurant_id', 'created_at']);
            $table->dropConstrainedForeignId('restaurant_id');
        });
    }
};
