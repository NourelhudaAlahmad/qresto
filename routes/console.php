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

// Retention (#57): closed-out sessions, dead carts and old webhook
// replay guards. Periods live in config('qresto.retention').
Schedule::command('model:prune')
    ->daily()
    ->withoutOverlapping();
