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
    $logContent = 'No log file';
    if (file_exists(storage_path('logs/laravel.log'))) {
        $raw = file_get_contents(storage_path('logs/laravel.log'));
        // Grab last 12000 chars but find the last "production.ERROR" block
        $tail = substr($raw, -12000);
        $lastError = strrpos($tail, 'production.ERROR');
        $logContent = $lastError !== false ? substr($tail, $lastError) : $tail;
    }

    // Compile and read the compiled view to inspect the error line
    $bladeCompileError = null;
    $compiledViewLines = null;
    try {
        $compiler = app(\Illuminate\View\Compilers\BladeCompiler::class);
        $viewPath = resource_path('views/standalone/public/profile.blade.php');
        if (file_exists($viewPath)) {
            $compiler->compile($viewPath);
            $bladeCompileError = 'compiled_ok';
            // Read the compiled file around the error line (1227)
            $compiledPath = $compiler->getCompiledPath($viewPath);
            if (file_exists($compiledPath)) {
                $lines = file($compiledPath);
                $total = count($lines);
                // Count PHP if/endif to find imbalance
                $ifCount = 0; $endifCount = 0; $ifLines = []; $endifLines = [];
                foreach ($lines as $lineNo => $line) {
                    if (preg_match('/^\s*<\?php\s+if\s*\(/', $line)) {
                        $ifCount++; $ifLines[] = $lineNo + 1;
                    }
                    if (preg_match('/^\s*<\?php\s+endif\s*;/', $line)) {
                        $endifCount++; $endifLines[] = $lineNo + 1;
                    }
                }
                $compiledViewLines = "total_lines:{$total}|php_if:{$ifCount}|php_endif:{$endifCount}"
                    . "\nIF_LINES(last10):" . implode(',', array_slice($ifLines, -10))
                    . "\nENDIF_LINES(last10):" . implode(',', array_slice($endifLines, -10));
            }
        } else {
            $bladeCompileError = 'view_file_not_found';
        }
    } catch (\Throwable $e) {
        $bladeCompileError = get_class($e) . ': ' . $e->getMessage() . ' at line ' . $e->getLine();
    }

    // Test-load the failing politician slug to surface the live exception
    $profileError = null;
    try {
        $politician = \App\Models\Politician::where('slug', '71112-us-representative-abraham-j-hamadeh')
            ->first();
        $profileError = $politician ? 'step1:found:id=' . $politician->id : 'not_found';
        if ($politician) {
            $politician->load(['page', 'initiatives' => fn($q) => $q->where('is_published', true)]);
            $profileError .= '|step2:page=' . ($politician->page ? 'yes' : 'no');
            $profileError .= '|initiatives=' . $politician->initiatives->count();

            // Step 3: campaigns with topics
            $running = $politician->campaigns()
                ->with('topics')
                ->where('approval_status', \App\Enums\ApprovalStatus::Approved)
                ->take(6)->get();
            $profileError .= '|step3:campaigns=' . $running->count();

            // Step 4: voter watch reports
            $qas = \App\Models\VoterWatchReport::query()
                ->messages()
                ->whereHas('campaign', fn($q) => $q->where('politician_id', $politician->id))
                ->take(5)->get();
            $profileError .= '|step4:qa=' . $qas->count();

            // Step 5: ElectionCandidateRecord
            $rec = \App\Models\ElectionCandidateRecord::where('state', $politician->state)
                ->whereRaw('LOWER(full_name) = ?', [strtolower((string)$politician->full_name)])
                ->first();
            $profileError .= '|step5:record=' . ($rec ? 'yes' : 'no');

            // Step 6: route generation
            $url = route('politician.public.show', '71112-us-representative-abraham-j-hamadeh');
            $profileError .= '|step6:route=ok';
        }
    } catch (\Throwable $e) {
        $profileError .= '|ERROR:' . get_class($e) . ': ' . $e->getMessage() . ' in ' . basename($e->getFile()) . ':' . $e->getLine();
    }

    return response()->json([
        'status' => 'ok',
        'php_version' => PHP_VERSION,
        'laravel_version' => app()->version(),
        'app_key_set' => !empty(config('app.key')),
        'db_connection' => config('database.default'),
        'env' => app()->environment(),
        'debug' => config('app.debug'),
        'blade_compile' => $bladeCompileError,
        'compiled_view_excerpt' => $compiledViewLines,
        'profile_probe' => $profileError,
        'recent_log' => $logContent,
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
