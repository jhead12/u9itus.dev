<?php

use App\Http\Controllers\ProfileController;
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
    return view('welcome');
});

Route::get('/debug-info', function () {
    return response()->json([
        'status' => 'ok',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'app_key_set' => !empty(config('app.key')),
        'db_connection' => config('database.default'),
        'storage_writable' => is_writable(storage_path()),
        'cache_writable' => is_writable(storage_path('framework/cache')),
        'views_writable' => is_writable(storage_path('framework/views')),
        'env' => app()->environment(),
        'debug' => config('app.debug'),
        'view_exists' => view()->exists('welcome'),
        'recent_log' => file_exists(storage_path('logs/laravel.log')) 
            ? substr(file_get_contents(storage_path('logs/laravel.log')), -2000)
            : 'No log file',
    ]);
});

// Legacy routes - kept for backward compatibility
// These are now primarily handled in standalone.php
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
