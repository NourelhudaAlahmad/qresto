<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LiveOrderController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\QrController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

if (app()->environment('local')) {
    Route::inertia('dev/components', 'dev/components')
        ->name('dev.components');
}

Route::post('/locale', [LocaleController::class, 'update'])
    ->name('locale.update');
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');

    Route::get('/live/orders', LiveOrderController::class)
        ->name('live.orders');
});
Route::get('/qr/{qrToken}', [QrController::class, 'scan'])
    ->name('qr.scan');

require __DIR__.'/settings.php';
