<?php

use App\Http\Controllers\AdminReservationController;
use App\Http\Controllers\CommonAreaBlockController;
use App\Http\Controllers\CommonAreaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PortariaOrderController;
use App\Http\Controllers\PortariaOrderHistoryController;
use App\Http\Controllers\PortariaVisitorAccessController;
use App\Http\Controllers\PortariaVisitorAccessHistoryController;
use App\Http\Controllers\PortariaVisitorEntryController;
use App\Http\Controllers\PortariaVisitorValidationController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ResidentCommonAreaController;
use App\Http\Controllers\ResidentOrderController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitorAuthorizationController;
use App\Http\Controllers\VisitorInvitationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');
Route::prefix('convites/{token}')->where(['token' => '[A-Za-z0-9]{64}'])->middleware('throttle:visitor-invitation')->group(function () {
    Route::get('/', [VisitorInvitationController::class, 'show'])->name('visitor-invitations.show');
    Route::post('/', [VisitorInvitationController::class, 'complete'])->name('visitor-invitations.complete');
});

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'admin'])->name('dashboard');
        Route::get('reservations', [AdminReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/{reservation}', [AdminReservationController::class, 'show'])->whereNumber('reservation')->name('reservations.show');
        Route::patch('reservations/{reservation}/approve', [AdminReservationController::class, 'approve'])->whereNumber('reservation')->name('reservations.approve');
        Route::patch('reservations/{reservation}/reject', [AdminReservationController::class, 'reject'])->whereNumber('reservation')->name('reservations.reject');
        Route::patch('reservations/{reservation}/cancel', [AdminReservationController::class, 'cancel'])->whereNumber('reservation')->name('reservations.cancel');

        Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])
            ->name('users.status.update');

        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
        Route::get('common-areas/{commonArea}/availability', [CommonAreaController::class, 'availability'])
            ->name('common-areas.availability');
        Route::resource('common-areas', CommonAreaController::class)->only(['index', 'store', 'update']);
        Route::resource('common-area-blocks', CommonAreaBlockController::class)
            ->parameters(['common-area-blocks' => 'commonAreaBlock'])
            ->only(['index', 'store', 'destroy'])->whereNumber('commonAreaBlock');
    });

    Route::prefix('morador')->name('morador.')->middleware('role:morador')->group(function () {
        Route::resource('orders', ResidentOrderController::class)->only(['index', 'store', 'show'])->whereNumber('order');
        Route::patch('orders/{order}/pickup', [ResidentOrderController::class, 'pickup'])->name('orders.pickup');
        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])
            ->whereNumber('notification')
            ->name('notifications.read');
        Route::get('dashboard', [DashboardController::class, 'morador'])->name('dashboard');
        Route::post('reservations', [ReservationController::class, 'store'])->name('reservations.store');
        Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/{reservation}', [ReservationController::class, 'show'])->whereNumber('reservation')->name('reservations.show');
        Route::patch('reservations/{reservation}/cancel', [ReservationController::class, 'cancel'])->whereNumber('reservation')->name('reservations.cancel');
        Route::get('common-areas', [ResidentCommonAreaController::class, 'index'])->name('common-areas.index');
        Route::get('common-areas/{commonArea}/availability', [ResidentCommonAreaController::class, 'availability'])
            ->name('common-areas.availability');
        Route::get('visitors/{visitorAuthorization}/qr-code', [VisitorAuthorizationController::class, 'qrCode'])
            ->middleware('throttle:visitor-qr-code')
            ->name('visitors.qr-code');
        Route::get('visitors/{visitorAuthorization}/access-code', [VisitorAuthorizationController::class, 'accessCode'])
            ->middleware('throttle:visitor-qr-code')
            ->name('visitors.access-code');
        Route::resource('visitors', VisitorAuthorizationController::class)
            ->parameters(['visitors' => 'visitorAuthorization'])
            ->only(['index', 'show']);
        Route::post('visitors', [VisitorAuthorizationController::class, 'store'])
            ->middleware('throttle:visitor-authorization')
            ->name('visitors.store');
        Route::delete('visitors/{visitorAuthorization}', [VisitorAuthorizationController::class, 'destroy'])
            ->middleware('throttle:visitor-authorization')
            ->name('visitors.destroy');
        Route::post('visitor-invitations', [VisitorInvitationController::class, 'store'])
            ->middleware('throttle:visitor-authorization')
            ->name('visitor-invitations.store');
    });

    Route::prefix('portaria')->name('portaria.')->middleware('role:porteiro')->group(function () {
        Route::resource('orders', PortariaOrderController::class)->only(['index', 'store']);
        Route::get('order-history', [PortariaOrderHistoryController::class, 'index'])->name('order-history.index');
        Route::get('orders/{order}', [PortariaOrderHistoryController::class, 'show'])->whereNumber('order')->name('orders.show');
        Route::patch('orders/{order}/pickup', [PortariaOrderController::class, 'pickup'])->name('orders.pickup');
        Route::patch('orders/{order}/receive', [PortariaOrderController::class, 'receive'])->name('orders.receive');
        Route::get('dashboard', [DashboardController::class, 'porteiro'])->name('dashboard');
        Route::get('visitor-access-history', [PortariaVisitorAccessHistoryController::class, 'index'])
            ->name('visitor-access-history.index');
        Route::get('visitor-accesses', [PortariaVisitorAccessController::class, 'index'])
            ->name('visitor-accesses.index');
        Route::post('visitor-accesses/{visitorAccess}/exit', [PortariaVisitorAccessController::class, 'registerExit'])
            ->middleware('throttle:visitor-portaria-exit')
            ->name('visitor-accesses.exit');
        Route::get('visitor-authorizations/validate', [PortariaVisitorValidationController::class, 'index'])
            ->name('visitor-authorizations.validation');
        Route::post('visitor-authorizations/validate', PortariaVisitorValidationController::class)
            ->middleware('throttle:visitor-portaria-validation')
            ->name('visitor-authorizations.validate');
        Route::post('visitor-accesses', PortariaVisitorEntryController::class)
            ->middleware('throttle:visitor-portaria-entry')
            ->name('visitor-accesses.store');
    });
});

require __DIR__.'/settings.php';
