<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\PortariaVisitorAccessController;
use App\Http\Controllers\PortariaVisitorAccessHistoryController;
use App\Http\Controllers\PortariaVisitorEntryController;
use App\Http\Controllers\PortariaVisitorValidationController;
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

        Route::patch('users/{user}/status', [UserController::class, 'updateStatus'])
            ->name('users.status.update');

        Route::resource('users', UserController::class)->only(['index', 'store', 'update']);
    });

    Route::prefix('morador')->name('morador.')->middleware('role:morador')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'morador'])->name('dashboard');
        Route::get('visitors/{visitorAuthorization}/qr-code', [VisitorAuthorizationController::class, 'qrCode'])
            ->name('visitors.qr-code');
        Route::get('visitors/{visitorAuthorization}/access-code', [VisitorAuthorizationController::class, 'accessCode'])
            ->name('visitors.access-code');
        Route::resource('visitors', VisitorAuthorizationController::class)
            ->parameters(['visitors' => 'visitorAuthorization'])
            ->only(['index', 'show', 'store', 'destroy']);
        Route::post('visitor-invitations', [VisitorInvitationController::class, 'store'])->name('visitor-invitations.store');
    });

    Route::prefix('portaria')->name('portaria.')->middleware('role:porteiro')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'porteiro'])->name('dashboard');
        Route::get('visitor-access-history', [PortariaVisitorAccessHistoryController::class, 'index'])
            ->name('visitor-access-history.index');
        Route::get('visitor-accesses', [PortariaVisitorAccessController::class, 'index'])
            ->name('visitor-accesses.index');
        Route::post('visitor-accesses/{visitorAccess}/exit', [PortariaVisitorAccessController::class, 'registerExit'])
            ->middleware('throttle:30,1')
            ->name('visitor-accesses.exit');
        Route::get('visitor-authorizations/validate', [PortariaVisitorValidationController::class, 'index'])
            ->name('visitor-authorizations.validation');
        Route::post('visitor-authorizations/validate', PortariaVisitorValidationController::class)
            ->middleware('throttle:30,1')
            ->name('visitor-authorizations.validate');
        Route::post('visitor-accesses', PortariaVisitorEntryController::class)
            ->middleware('throttle:30,1')
            ->name('visitor-accesses.store');
    });
});

require __DIR__.'/settings.php';
