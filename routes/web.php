<?php

use App\Http\Controllers\Auth\ManagerShiftQrController;
use App\Http\Controllers\Auth\ShiftQrAuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartLineController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\WaiterOrderController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

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

// Staff Shift QR Authentication

Route::post(
    '/auth/shift-qr',
    [ShiftQrAuthController::class, 'store'],
)->middleware([
    'guest:web',
    'throttle:10,1',
])->name('auth.shift-qr.store');

Route::post(
    '/auth/shift-qr/issue',
    [ShiftQrAuthController::class, 'issue'],
)->middleware([
    'auth:web',
    'verified',
    'throttle:10,1',
])->name('auth.shift-qr.issue');

// Manager-only Shift QR page

Route::get(
    '/manager/shift-qr',
    [ManagerShiftQrController::class, 'index'],
)->middleware([
    'auth:web',
    'verified',
])->name('manager.shift-qr.index');

// Authenticated dashboard and staff actions

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->name('dashboard');

    // Temporary role landing routes for Issue #21.
    // Full staff panels will be implemented in their own issues.
    $staffLandings = [
        'waiter' => [
            'uri' => 'waiter/orders',
            'name' => 'waiter.orders',
            'title' => 'My Orders',
        ],
        'kitchen' => [
            'uri' => 'kitchen/pass',
            'name' => 'kitchen.pass',
            'title' => 'Kitchen Pass',
        ],
        'manager' => [
            'uri' => 'manager/overview',
            'name' => 'manager.overview',
            'title' => 'Manager Overview',
        ],
        'admin' => [
            'uri' => 'admin/system',
            'name' => 'admin.system',
            'title' => 'Admin System',
        ],
    ];

    foreach ($staffLandings as $role => $landing) {
        Route::get($landing['uri'], function () use ($role, $landing) {
            abort_unless(
                request()->user()?->hasRole($role),
                403,
            );

            return Inertia::render('staff/landing', [
                'title' => $landing['title'],
            ]);
        })->name($landing['name']);
    }

    Route::patch(
        '/service-requests/{serviceRequest}/acknowledge',
        [ServiceRequestController::class, 'acknowledge'],
    )->name('service-requests.acknowledge');
});

// Staff application routes — Issues #22 and #23

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/app/overview', function () {
        return Inertia::render('staff/landing', [
            'title' => 'Overview',
        ]);
    })
        ->middleware('can:see_all_orders')
        ->name('app.overview');

    // Manager: view all restaurant orders.
    Route::get(
        '/app/orders',
        [WaiterOrderController::class, 'index'],
    )
        ->middleware('can:see_all_orders')
        ->name('app.orders');

    Route::get('/app/floor', function () {
        return Inertia::render('staff/landing', [
            'title' => 'Floor',
        ]);
    })
        ->middleware('can:see_own_tables')
        ->name('app.floor');

    Route::get('/app/reports', function () {
        return Inertia::render('staff/landing', [
            'title' => 'Reports',
        ]);
    })
        ->middleware('can:financial_reports')
        ->name('app.reports');

    // Waiter: view assigned orders only.
    Route::get(
        '/app/my-orders',
        [WaiterOrderController::class, 'index'],
    )
        ->middleware('can:see_own_tables')
        ->name('app.my-orders');

    // Advance one valid order status at a time.
    Route::patch(
        '/app/orders/{order}/advance',
        [WaiterOrderController::class, 'advance'],
    )
        ->middleware('can:change_order_status')
        ->name('app.orders.advance');

    // Void an order with a required reason.
    Route::post(
        '/app/orders/{order}/void',
        [WaiterOrderController::class, 'void'],
    )
        ->middleware('can:change_order_status')
        ->name('app.orders.void');

    Route::get('/app/system', function () {
        return Inertia::render('staff/landing', [
            'title' => 'System',
        ]);
    })
        ->middleware('can:manage_restaurant_config')
        ->name('app.system');

    Route::get('/app/menu', function () {
        return Inertia::render('staff/landing', [
            'title' => 'Menu',
        ]);
    })
        ->middleware('can:edit_menu_prices')
        ->name('app.menu');

    Route::get('/app/pass', function () {
        return Inertia::render('staff/landing', [
            'title' => 'Kitchen Pass',
        ]);
    })
        ->middleware('can:see_all_orders')
        ->name('app.pass');
});

// Guest table QR routes

Route::middleware('throttle:30,1')->group(function () {
    Route::get('/t/{qrToken}', [QrController::class, 'show'])
        ->name('table.show');

    Route::post('/t/{qrToken}/confirm', [QrController::class, 'confirm'])
        ->name('table.confirm');

    Route::get('/t/{qrToken}/tables', [QrController::class, 'tables'])
        ->name('table.tables');
});

// Guest table session routes

Route::middleware('table.session')->group(function () {
    Route::get('/menu', [MenuController::class, 'index'])
        ->name('menu');

    Route::get('/menu/{menuItem}', [MenuItemController::class, 'show'])
        ->name('menu.items.show');

    Route::get('/cart', [CartController::class, 'index'])
        ->name('cart');

    Route::patch('/cart/note', [CartController::class, 'updateNote'])
        ->name('cart.note.update');

    Route::post('/cart/lines', [CartLineController::class, 'store'])
        ->name('cart.lines.store');

    Route::patch('/cart/lines/{line}', [CartLineController::class, 'update'])
        ->name('cart.lines.update');

    Route::delete('/cart/lines/{line}', [CartLineController::class, 'destroy'])
        ->name('cart.lines.destroy');

    Route::post(
        '/cart/lines/{line}/restore',
        [CartLineController::class, 'restore'],
    )->name('cart.lines.restore');

    Route::get('/checkout', [CheckoutController::class, 'show'])
        ->name('checkout');

    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->name('checkout.store');

    Route::post('/checkout/confirm', [CheckoutController::class, 'confirm'])
        ->name('checkout.confirm');

    Route::post(
        '/checkout/promo',
        [CheckoutController::class, 'applyPromo'],
    )->name('checkout.promo.store');

    Route::delete(
        '/checkout/promo',
        [CheckoutController::class, 'removePromo'],
    )->name('checkout.promo.destroy');

    Route::post(
        '/service-requests',
        [ServiceRequestController::class, 'store'],
    )->name('guest.service-requests.store');

    Route::get(
        '/orders/{order}/status',
        [OrderStatusController::class, 'show'],
    )->name('guest.orders.status');
});

require __DIR__.'/settings.php';
