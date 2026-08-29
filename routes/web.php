<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardRedirectController;
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
        Route::resource('visitors', VisitorAuthorizationController::class)
            ->parameters(['visitors' => 'visitorAuthorization'])
            ->only(['index', 'show']);
    });

    Route::get('portaria/dashboard', [DashboardController::class, 'porteiro'])
        ->middleware('role:porteiro')
        ->name('portaria.dashboard');
});

require __DIR__.'/settings.php';
