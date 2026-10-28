<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Things that stop service when they fail silently (#30): the payment
     * gateway, kitchen display screens, receipt printers. `status` is what
     * the device last reported; the health shown to people is derived from
     * the heartbeat age, so a row that stops reporting cannot stay green.
     */
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('restaurant_id')
                ->constrained('restaurants')
                ->cascadeOnDelete();

            $table->string('kind', 20); // gateway | kds | printer
            $table->string('name', 80);

            // Last reported state: ok | warning | failing.
            $table->string('status', 20)->nullable();
            $table->string('detail', 160)->nullable();
            $table->timestamp('last_heartbeat_at')->nullable();
            $table->json('meta')->nullable();

            // SHA-256 of the device's bearer token. The plain token is shown
            // once, when it is issued.
            $table->string('token_hash', 64)->nullable()->unique();

            // Only for "notify once": the last health managers were told
            // about. Never shown as the health itself.
            $table->string('alerted_health', 20)->nullable();

            $table->timestamps();

            $table->index(['restaurant_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
