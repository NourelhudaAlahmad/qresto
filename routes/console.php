<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('qresto:close-idle-table-sessions')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// System health (#30).
Schedule::command('qresto:probe-gateway')
    ->everyFiveMinutes()
    ->withoutOverlapping();

Schedule::command('qresto:check-integrations')
    ->everyMinute()
    ->withoutOverlapping();

// Retention (#57): closed-out sessions, dead carts, old webhook replay
// guards and spent shift codes. Periods live in config('qresto.retention').
Schedule::command('model:prune')
    ->daily()
    ->withoutOverlapping();

Schedule::command('qresto:prune-notifications')
    ->daily()
    ->withoutOverlapping();

Schedule::command('queue:prune-failed', [
    '--hours' => (int) config('qresto.retention.failed_jobs_hours', 168),
])->daily();

// Expired password-reset and staff-invitation tokens.
Schedule::command('auth:clear-resets', ['users'])->daily();
Schedule::command('auth:clear-resets', ['staff_invites'])->daily();
