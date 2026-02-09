<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| The primary UI is rendered inside Wix iframes (see routes/wix.php).
| These web routes handle the Laravel-native auth pages and profile.
|
*/

Route::get('/', function () {
    try {
        // Test basic env loading
        $appKey = config('app.key') ? 'set' : 'not set';
        $appEnv = config('app.env', 'unknown');
        
        // Test route availability
        $hasLogin = Route::has('login');
        $hasRegister = Route::has('register');
        
        // If all checks pass, render the view
        return view('welcome');
    } catch (\Exception $e) {
        // Return error details for debugging
        return response()->json([
            'error' => 'Failed to load home page',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'app_key_set' => !empty(env('APP_KEY')),
            'app_env' => env('APP_ENV', 'not set'),
        ], 500);
    }
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
        'env' => app()->environment(),
        'debug' => config('app.debug'),
    ]);
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
