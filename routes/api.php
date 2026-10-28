<?php

use App\Http\Controllers\Api\IntegrationHeartbeatController;
use Illuminate\Support\Facades\Route;

// System health heartbeats (#30): KDS screens, printer agents.
Route::post('/integrations/{integration}/heartbeat', IntegrationHeartbeatController::class)
    ->whereNumber('integration')
    ->middleware('throttle:120,1')
    ->name('api.integrations.heartbeat');
