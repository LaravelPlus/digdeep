<?php

use Illuminate\Support\Facades\Route;
use LaravelPlus\DigDeep\Controllers\ApiController;
use LaravelPlus\DigDeep\Controllers\DashboardController;
use LaravelPlus\DigDeep\Middleware\Authorize;

Route::prefix('digdeep')->middleware(Authorize::class)->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('digdeep.dashboard');
    Route::get('/pipeline', [DashboardController::class, 'pipeline'])->name('digdeep.pipeline');
    Route::get('/security', [DashboardController::class, 'security'])->name('digdeep.security');
    Route::get('/audits', [DashboardController::class, 'audits'])->name('digdeep.audits');
    Route::get('/urls', [DashboardController::class, 'urls'])->name('digdeep.urls');
    Route::get('/database', [DashboardController::class, 'database'])->name('digdeep.database');
    Route::get('/errors', [DashboardController::class, 'errors'])->name('digdeep.errors');
    Route::get('/compare', [DashboardController::class, 'compare'])->name('digdeep.compare');
    Route::get('/profiler', [DashboardController::class, 'profiler'])->name('digdeep.profiler');
    Route::get('/trends', [DashboardController::class, 'trends'])->name('digdeep.trends');
    Route::get('/performance', [DashboardController::class, 'performance'])->name('digdeep.performance');
    Route::get('/cache', [DashboardController::class, 'cache'])->name('digdeep.cache');
    Route::get('/profile/{id}', [DashboardController::class, 'show'])->name('digdeep.show');

    // API routes
    Route::post('/api/trigger', [ApiController::class, 'trigger'])->name('digdeep.trigger');
    Route::post('/api/clear', [ApiController::class, 'clear'])->name('digdeep.clear');
    Route::delete('/api/profile/{id}', [ApiController::class, 'destroy'])->name('digdeep.destroy');
    Route::get('/api/profile/{id}/export', [ApiController::class, 'export'])->name('digdeep.export');
    Route::post('/api/profile/{id}/replay', [ApiController::class, 'replay'])->name('digdeep.replay');
    Route::post('/api/profile/{id}/tags', [ApiController::class, 'updateTags'])->name('digdeep.tags');
    Route::post('/api/profile/{id}/notes', [ApiController::class, 'updateNotes'])->name('digdeep.notes');
    Route::post('/api/explain', [ApiController::class, 'explain'])->name('digdeep.explain');
    Route::get('/api/profiles', [ApiController::class, 'profiles'])->name('digdeep.api.profiles');
    Route::get('/api/trends', [ApiController::class, 'trends'])->name('digdeep.api.trends');
    Route::post('/api/bulk', [ApiController::class, 'bulkAction'])->name('digdeep.api.bulk');
    Route::get('/api/performance', [ApiController::class, 'performanceData'])->name('digdeep.api.performance');
    Route::get('/api/compare', [ApiController::class, 'compareData'])->name('digdeep.api.compare');
    Route::post('/api/bulk-export', [ApiController::class, 'bulkExport'])->name('digdeep.api.bulkExport');
});
