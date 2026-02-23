<?php

use Illuminate\Support\Facades\Route;
use LaravelPlus\DigDeep\Controllers\ApiController;
use LaravelPlus\DigDeep\Controllers\DashboardController;

Route::prefix('digdeep')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('digdeep.dashboard');
    Route::get('/security', [DashboardController::class, 'security'])->name('digdeep.security');
    Route::get('/audits', [DashboardController::class, 'audits'])->name('digdeep.audits');
    Route::get('/urls', [DashboardController::class, 'urls'])->name('digdeep.urls');
    Route::get('/database', [DashboardController::class, 'database'])->name('digdeep.database');
    Route::get('/errors', [DashboardController::class, 'errors'])->name('digdeep.errors');
    Route::get('/profile/{id}', [DashboardController::class, 'show'])->name('digdeep.show');
    Route::post('/api/trigger', [ApiController::class, 'trigger'])->name('digdeep.trigger');
    Route::post('/api/clear', [ApiController::class, 'clear'])->name('digdeep.clear');
    Route::delete('/api/profile/{id}', [ApiController::class, 'destroy'])->name('digdeep.destroy');
});
