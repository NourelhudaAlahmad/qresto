<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\PermissionMatrixController;
use App\Http\Controllers\Auth\ManagerShiftQrController;
use App\Http\Controllers\Auth\ShiftQrAuthController;
use App\Http\Controllers\Auth\StaffInvitationController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CartLineController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailReceiptController;
use App\Http\Controllers\FloorController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ManagerDashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\MenuItemController;
use App\Http\Controllers\OrderStatusController;
use App\Http\Controllers\QrController;
use App\Http\Controllers\ReceiptController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\WaiterOrderController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/locale', [LocaleController::class, 'update'])
    ->name('locale.update');

Route::get('/', [LandingController::class, 'show'])
    ->name('home');

Route::get('/r/{restaurant:slug}', [LandingController::class, 'show'])
    ->name('restaurants.show');

if (app()->environment('local')) {
    Route::inertia('dev/components', 'dev/components')
        ->name('dev.components');
}

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

// Staff invitation — the set-password link a new staff member receives (#27).

Route::middleware(['guest:web', 'throttle:10,1'])->group(function () {
    Route::get(
        '/staff/invitation/{token}',
        [StaffInvitationController::class, 'show'],
    )->name('staff.invitation.show');

    Route::post(
        '/staff/invitation',
        [StaffInvitationController::class, 'store'],
    )->name('staff.invitation.store');
});

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

    // Manager Dashboard — Issue #26

    Route::get(
        'manager/overview',
        [ManagerDashboardController::class, 'index'],
    )->name('manager.overview');

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

// Staff application routes — Issues #22, #23, #24 and #25

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

    // Issue #25 — Floor Map

    Route::get(
        '/app/floor',
        [FloorController::class, 'index'],
    )
        ->middleware('can:see_own_tables')
        ->name('app.floor');

    // Table details and itemized open bill.

    Route::get(
        '/app/tables/{table}',
        [FloorController::class, 'show'],
    )
        ->middleware('can:see_own_tables')
        ->name('app.tables.show');

    // Seat guests at a free table.

    Route::post(
        '/app/tables/{table}/seat',
        [FloorController::class, 'seat'],
    )
        ->middleware('can:see_own_tables')
        ->name('app.tables.seat');

    // Free a table after all unpaid bills are settled.

    Route::post(
        '/app/tables/{table}/free',
        [FloorController::class, 'free'],
    )
        ->middleware('can:see_own_tables')
        ->name('app.tables.free');

    // Settle cash or POS payment at a table.
    // Supports full and partial payments.

    Route::post(
        '/app/tables/{table}/payments',
        [FloorController::class, 'payment'],
    )
        ->middleware('can:take_payment')
        ->name('app.tables.payments');

    // Issue #25 — Print Receipt.
    // Supports paid and partially paid orders.

    Route::get(
        '/app/tables/{table}/orders/{order}/receipt',
        [ReceiptController::class, 'show'],
    )
        ->middleware('can:see_own_tables')
        ->name('app.tables.orders.receipt');

    // Issue #25 — Email Receipt.

    Route::post(
        '/app/tables/{table}/orders/{order}/receipt/email',
        [EmailReceiptController::class, 'store'],
    )
        ->middleware('can:take_payment')
        ->name('app.tables.orders.receipt.email');

    // Other staff application routes

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

    // Issue #26 — Manager order reassignment.

    Route::patch(
        '/app/orders/{order}/assign',
        [ManagerDashboardController::class, 'assign'],
    )
        ->middleware('can:manage_waiter_accounts')
        ->name('app.orders.assign');

    // Issue #27 — Staff management from the manager overview drawer.

    Route::middleware('can:manage_waiter_accounts')->group(function () {
        Route::post('/app/staff', [StaffController::class, 'store'])
            ->name('app.staff.store');

        Route::patch('/app/staff/{member}', [StaffController::class, 'update'])
            ->name('app.staff.update');

        Route::post(
            '/app/staff/{member}/deactivate',
            [StaffController::class, 'deactivate'],
        )->name('app.staff.deactivate');

        Route::post(
            '/app/staff/{member}/reactivate',
            [StaffController::class, 'reactivate'],
        )->name('app.staff.reactivate');

        Route::post(
            '/app/staff/{member}/invite',
            [StaffController::class, 'invite'],
        )->name('app.staff.invite');
    });

    // Issue #28 — Super admin dashboard and the editable permission matrix.

    Route::middleware('can:manage_restaurant_config')->group(function () {
        Route::get('/app/system', [AdminDashboardController::class, 'index'])
            ->name('app.system');

        Route::get(
            '/app/system/permissions',
            [PermissionMatrixController::class, 'index'],
        )->name('app.system.permissions.index');

        Route::patch(
            '/app/system/permissions',
            [PermissionMatrixController::class, 'update'],
        )->name('app.system.permissions.update');

        Route::get(
            '/app/system/permissions/audits',
            [PermissionMatrixController::class, 'audits'],
        )->name('app.system.permissions.audits');
    });

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
