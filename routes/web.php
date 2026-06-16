<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\NotificationPreferenceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| U9itus Standalone Application Routes
| Framework: Laravel 12 (Standalone Architecture)
|
*/

// Diagnostic endpoint - always works
Route::get('/diagnose', function () {
    return response()->json([
        'status' => 'Laravel is running',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'app_key_set' => !empty(config('app.key')),
        'app_env' => config('app.env'),
        'app_debug' => config('app.debug'),
        'routes_loaded' => Route::has('login'),
        'view_exists' => view()->exists('welcome'),
    ]);
});

/*
|--------------------------------------------------------------------------
| Standalone Routes
|--------------------------------------------------------------------------
*/

require __DIR__.'/standalone.php';

/*
|--------------------------------------------------------------------------
| Shared Application Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    $referralCode = request()->session()->get('referral.code')
        ?: request()->cookie('u9_referral_code');

    return view('welcome', [
        'referralCode' => $referralCode,
    ]);
});

Route::get('/about', function () {
    return view('about');
})->name('about');

Route::get('/terms', function () {
    return view('terms');
})->name('terms');

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
})->name('privacy-policy');

Route::get('/debug-info', function () {
    return response()->json([
        'status' => 'ok',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'app_key_set' => !empty(config('app.key')),
        'db_connection' => config('database.default'),
        'storage_writable' => is_writable(storage_path()),
        'env' => app()->environment(),
        'debug' => config('app.debug'),
        'recent_log' => file_exists(storage_path('logs/laravel.log'))
            ? substr(file_get_contents(storage_path('logs/laravel.log')), -2000)
            : 'No log file',
    ]);
});

// NOTE: The /dashboard route and its name are fully owned by standalone.php
// (DashboardController@index). The old closure was removed to prevent it from
// overriding the named route in cached environments and sending authenticated
// users to the generic placeholder view.

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    // Notification preferences
    Route::get('/notification-preferences', [NotificationPreferenceController::class, 'edit'])->name('notification-preferences.edit');
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');
    Route::post('/notification-preferences/fcm-token', [NotificationPreferenceController::class, 'storeFcmToken'])->name('notification-preferences.fcm-token');
});

// auth.php routes are intentionally excluded — all auth/verification/logout
// routes are handled by routes/standalone.php (standalone architecture).
// Keeping this file required causes duplicate route name conflicts.
// require __DIR__.'/auth.php';
