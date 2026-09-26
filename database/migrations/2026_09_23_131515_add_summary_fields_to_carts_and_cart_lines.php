<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->text('note')
                ->nullable()
                ->after('currency');
        });

        Schema::table('cart_lines', function (Blueprint $table) {
            $table->timestamp('removed_at')
                ->nullable()
                ->after('note');

            $table->string('undo_token', 64)
                ->nullable()
                ->unique()
                ->after('removed_at');

            $table->timestamp('undo_expires_at')
                ->nullable()
                ->after('undo_token');

            $table->index([
                'cart_id',
                'removed_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('cart_lines', function (Blueprint $table) {
            $table->dropIndex([
                'cart_id',
                'removed_at',
            ]);

            $table->dropUnique([
                'undo_token',
            ]);

            $table->dropColumn([
                'removed_at',
                'undo_token',
                'undo_expires_at',
            ]);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
