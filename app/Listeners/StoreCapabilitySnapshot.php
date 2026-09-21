<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\CapabilitySnapshot;
use Illuminate\Auth\Events\Login;

class StoreCapabilitySnapshot
{
    /**
     * Handle the login event.
     */
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        app(CapabilitySnapshot::class)->store($event->user);
    }
}
