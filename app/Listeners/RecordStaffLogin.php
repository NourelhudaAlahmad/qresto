<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Auth\StaffAccountLockout;
use Illuminate\Auth\Events\Login;

class RecordStaffLogin
{
    public function __construct(
        private readonly StaffAccountLockout $lockout,
    ) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->lockout->recordSuccess($event->user);
    }
}
