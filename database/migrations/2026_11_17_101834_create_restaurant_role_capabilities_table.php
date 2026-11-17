<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One restaurant's changes to the default role → capability grid. The
 * global role permissions (RolesSeeder) stay the defaults; a row here
 * grants or revokes one capability for one role in one restaurant only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restaurant_role_capabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->string('capability');
            $table->boolean('granted');
            $table->timestamps();

            $table->unique(['restaurant_id', 'role', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restaurant_role_capabilities');
    }
};
