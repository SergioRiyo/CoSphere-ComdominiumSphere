<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\PortariaVisitorEntryController;
use App\Http\Controllers\PortariaVisitorValidationController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VisitorAuthorizationController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

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
            ->only(['index', 'show', 'store']);
    });

    Route::prefix('portaria')->name('portaria.')->middleware('role:porteiro')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'porteiro'])->name('dashboard');
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
