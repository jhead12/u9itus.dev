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

    // Resolve visitor state via ipinfo.io (cached 6h) — non-fatal.
    $visitorState = null;
    try {
        $ip = request()->ip();
        $apiKey = config('u9itus.fraud.ipinfo_api_key');
        if (!empty($ip) && !empty($apiKey) && filter_var($ip, FILTER_VALIDATE_IP)) {
            $visitorState = \Illuminate\Support\Facades\Cache::remember(
                "geo:state:{$ip}",
                now()->addHours(6),
                function () use ($ip, $apiKey) {
                    $resp = \Illuminate\Support\Facades\Http::timeout(2)
                        ->get("https://ipinfo.io/{$ip}/json", ['token' => $apiKey]);
                    if (!$resp->ok()) return null;
                    $region = $resp->json('region');
                    return is_string($region) && $region !== '' ? $region : null;
                }
            );
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::info('Welcome geo lookup failed', ['error' => $e->getMessage()]);
    }

    // Build featured candidates — 4 cards: 2 local (photo optional), rest nationwide.
    // Candidates with the most recent verified news are preferred.
    $featuredCandidates = collect();
    try {
        $hasNewsTable = \Illuminate\Support\Facades\Schema::hasTable('candidate_news_articles');

        $base = \App\Models\Politician::query()
            ->where('page_published', true)
            ->where('is_active', true)
            ->whereNotNull('slug');

        $orderByNewsRecency = function ($query) use ($hasNewsTable) {
            if ($hasNewsTable) {
                $query->orderByDesc(
                    \App\Models\CandidateNewsArticle::query()
                        ->selectRaw('MAX(published_at)')
                        ->whereColumn('politician_id', 'politicians.id')
                        ->where('verification_status', 'verified')
                );
            }
            return $query->inRandomOrder();
        };

        if ($visitorState) {
            // Local slots: allow missing photos, prefer freshest news.
            $featuredCandidates = $orderByNewsRecency(
                (clone $base)->where('state', $visitorState)
            )->limit(2)->get();
        }

        if ($featuredCandidates->count() < 4) {
            $needed = 4 - $featuredCandidates->count();
            $extras = $orderByNewsRecency(
                (clone $base)
                    ->whereNotNull('profile_photo_url')
                    ->when($featuredCandidates->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $featuredCandidates->pluck('id')))
            )->limit($needed)->get();
            $featuredCandidates = $featuredCandidates->concat($extras);
        }

        // Attach latest news snippet per candidate (single query).
        if ($featuredCandidates->isNotEmpty() && $hasNewsTable) {
            $ids = $featuredCandidates->pluck('id')->all();
            $newsByPolitician = \App\Models\CandidateNewsArticle::query()
                ->whereIn('politician_id', $ids)
                ->where('verification_status', 'verified')
                ->orderByDesc('published_at')
                ->get()
                ->groupBy('politician_id');
            $featuredCandidates->each(function ($p) use ($newsByPolitician) {
                $p->setAttribute('latest_news', optional($newsByPolitician->get($p->id))->first());
            });
        }
    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::warning('Featured candidates load failed', ['error' => $e->getMessage()]);
        $featuredCandidates = collect();
    }

    return view('welcome', [
        'referralCode'       => $referralCode,
        'featuredCandidates' => $featuredCandidates,
        'visitorState'       => $visitorState,
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

// WebMCP demo / tool catalogue — a live console for the civic-agent tools
// exposed via resources/js/webmcp/index.js. See doc/WEBMCP.md.
Route::view('/webmcp', 'webmcp')->name('webmcp');

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
