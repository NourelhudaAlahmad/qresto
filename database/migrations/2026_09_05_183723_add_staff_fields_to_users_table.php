<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')
                ->default(true)
                ->after('password');

            $table->string('initials', 10)
                ->nullable()
                ->after('is_active');

            $table->string('job_title')
                ->nullable()
                ->after('initials');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'is_active',
                'initials',
                'job_title',
            ]);
        });
    }
};
