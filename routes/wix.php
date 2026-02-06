<?php

/**
 * Wix App Extension Routes
 *
 * These routes handle:
 *   1. OAuth installation flow (install → consent → callback) — no auth
 *   2. Dashboard pages rendered inside Wix Dashboard iframes — wix.verify
 *   3. Widget pages rendered inside Wix site iframes — wix.verify
 *
 * Note: These routes use the 'web' middleware group (via bootstrap/app.php)
 * but CSRF is excluded for wix/* paths since requests come from Wix iframes.
 */

use App\Http\Controllers\Wix\OAuthController;
use App\Http\Controllers\Wix\DashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| OAuth Installation Flow (public — no auth)
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
| Protected by VerifyWixInstance middleware
|--------------------------------------------------------------------------
*/
Route::prefix('wix/dashboard')->name('wix.dashboard.')->middleware('wix.verify')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('index');
    Route::get('/politician', fn() => view('wix.dashboard.politician'))->name('politician');
    Route::get('/voter', fn() => view('wix.dashboard.voter'))->name('voter');
    Route::get('/admin', [DashboardController::class, 'admin'])->name('admin');
});

/*
|--------------------------------------------------------------------------
| Wix Site Widget (voter-facing video player embedded on Wix site)
| Protected by VerifyWixInstance middleware
|--------------------------------------------------------------------------
*/
Route::prefix('wix/widget')->name('wix.widget.')->middleware('wix.verify')->group(function () {
    Route::get('/', fn() => view('wix.widget.voter-feed'))->name('feed');
    Route::get('/settings', fn() => view('wix.widget.settings'))->name('settings');
});
