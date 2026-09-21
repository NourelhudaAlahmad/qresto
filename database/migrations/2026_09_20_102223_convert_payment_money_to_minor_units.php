<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('payments')->update([
            'amount' => DB::raw('ROUND(amount * 100)'),
            'tip_amount' => DB::raw('ROUND(tip_amount * 100)'),
            'refunded_amount' => DB::raw('ROUND(refunded_amount * 100)'),
        ]);

        Schema::table('payments', function (Blueprint $table) {
            $table->bigInteger('amount')
                ->change();

            $table->bigInteger('tip_amount')
                ->default(0)
                ->change();

            $table->bigInteger('refunded_amount')
                ->default(0)
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('payments')->update([
            'amount' => DB::raw('amount / 100'),
            'tip_amount' => DB::raw('tip_amount / 100'),
            'refunded_amount' => DB::raw('refunded_amount / 100'),
        ]);

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)
                ->change();

            $table->decimal('tip_amount', 10, 2)
                ->default(0)
                ->change();

            $table->decimal('refunded_amount', 10, 2)
                ->default(0)
                ->change();
        });
    }
};
