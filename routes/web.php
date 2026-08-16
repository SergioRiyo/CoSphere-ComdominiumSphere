<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DashboardRedirectController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'active', 'verified'])->group(function () {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');

    Route::get('admin/dashboard', [DashboardController::class, 'admin'])
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::get('morador/dashboard', [DashboardController::class, 'morador'])
        ->middleware('role:morador')
        ->name('morador.dashboard');

    Route::get('portaria/dashboard', [DashboardController::class, 'porteiro'])
        ->middleware('role:porteiro')
        ->name('portaria.dashboard');
});

require __DIR__.'/settings.php';
