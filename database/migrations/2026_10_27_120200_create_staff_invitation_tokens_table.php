<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set-password invitations get their own token table so a later
     * "forgot password" request cannot overwrite an unused invitation,
     * and so the two can have different expiry windows.
     */
    public function up(): void
    {
        Schema::create('staff_invitation_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_invitation_tokens');
    }
};
