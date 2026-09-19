<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\QrController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', [LandingController::class, 'show'])
    ->name('home');

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

Route::get('/menu', function (Request $request) {
    $session = $request->attributes->get('tableSession');

    return Inertia::render('menu', [
        'restaurant' => [
            'name' => $session->restaurant->name,
        ],
        'table' => [
            'number' => $session->table->number,
        ],
        'session' => [
            'active' => $session->isActive(),
        ],
    ]);
})
    ->middleware('table.session')
    ->name('menu');

require __DIR__.'/settings.php';
