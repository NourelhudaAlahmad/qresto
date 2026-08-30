php
<?php

use App\Http\Controllers\QrController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

if (app()->environment('local')) {
    Route::inertia('dev/components', 'dev/components')
        ->name('dev.components');
}

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

Route::get('/qr/{qrToken}', [QrController::class, 'scan'])
    ->name('qr.scan');

require __DIR__.'/settings.php';
