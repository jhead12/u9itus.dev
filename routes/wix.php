<?php

/**
 * Wix App Extension Routes
 *
 * These routes handle:
 *   1. OAuth installation flow (install → consent → callback)
 *   2. Dashboard pages rendered inside Wix Dashboard iframes
 *   3. Widget pages rendered inside Wix site iframes
 */

use App\Http\Controllers\Wix\OAuthController;
use App\Http\Controllers\Wix\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OAuth Installation Flow
|--------------------------------------------------------------------------
*/
Route::prefix('wix')->name('wix.')->group(function () {
    Route::get('/install', [OAuthController::class, 'install'])->name('install');
    Route::get('/oauth/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
    Route::get('/signup', [OAuthController::class, 'signup'])->name('signup');
});

/*
|--------------------------------------------------------------------------
| Wix Dashboard Pages (rendered inside Wix Dashboard iframe)
|--------------------------------------------------------------------------
*/
Route::prefix('wix/dashboard')->name('wix.dashboard.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/politician', fn() => view('wix.dashboard.politician'))->name('politician');
    Route::get('/voter', fn() => view('wix.dashboard.voter'))->name('voter');
    Route::get('/admin', [DashboardController::class, 'admin'])->name('admin');
});

/*
|--------------------------------------------------------------------------
| Wix Site Widget (voter-facing video player embedded on Wix site)
|--------------------------------------------------------------------------
*/
Route::prefix('wix/widget')->name('wix.widget.')->group(function () {
    Route::get('/', fn() => view('wix.widget.voter-feed'))->name('feed');
    Route::get('/settings', fn() => view('wix.widget.settings'))->name('settings');
});
