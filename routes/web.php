<?php

use App\Http\Controllers\CartLineController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\QrController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'show'])
    ->name('home');

Route::get('/r/{restaurant:slug}', [LandingController::class, 'show'])
    ->name('restaurants.show');

if (app()->environment('local')) {
    Route::inertia('dev/components', 'dev/components')
        ->name('dev.components');
}

Route::post('/locale', [LocaleController::class, 'update'])
    ->name('locale.update');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');
});

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/t/{qrToken}', [QrController::class, 'show'])
        ->name('table.show');

    Route::post('/t/{qrToken}/confirm', [QrController::class, 'confirm'])
        ->name('table.confirm');

    Route::get('/t/{qrToken}/tables', [QrController::class, 'tables'])
        ->name('table.tables');
});

Route::middleware('table.session')->group(function () {
    Route::get('/menu', [MenuController::class, 'index'])
        ->name('menu');

    Route::get('/menu/{menuItem}', [MenuItemController::class, 'show'])
        ->name('menu.items.show');

    Route::post('/cart/lines', [CartLineController::class, 'store'])
        ->name('cart.lines.store');
});

require __DIR__.'/settings.php';
