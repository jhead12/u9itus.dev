<?php

use App\Http\Controllers\Admin\AssignmentController as AdminAssignmentController;
use App\Http\Controllers\Advertiser\CampaignController;
use App\Http\Controllers\Advertiser\DashboardController as AdvertiserDashboardController;
use App\Http\Controllers\Viewer\DashboardController as ViewerDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->group(function () {
    // Admin routes
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/assignments', [AdminAssignmentController::class, 'index'])->name('assignments.index');
        Route::post('/assignments/assign', [AdminAssignmentController::class, 'assignAd'])->name('assignments.assign');
        Route::post('/assignments/auto-assign', [AdminAssignmentController::class, 'autoAssign'])->name('assignments.auto-assign');
    });

    // Advertiser routes
    Route::prefix('advertiser')->name('advertiser.')->group(function () {
        Route::get('/dashboard', [AdvertiserDashboardController::class, 'index'])->name('dashboard');
        Route::resource('campaigns', CampaignController::class);
    });

    // Viewer routes
    Route::prefix('viewer')->name('viewer.')->group(function () {
        Route::get('/dashboard', [ViewerDashboardController::class, 'index'])->name('dashboard');
        Route::get('/watch/{assignment}', [ViewerDashboardController::class, 'watch'])->name('watch');
        Route::post('/complete/{assignment}', [ViewerDashboardController::class, 'complete'])->name('complete');
    });
});
