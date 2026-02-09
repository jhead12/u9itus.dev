<?php

/**
 * API Routes for Dial4Dough – Political Loyalty Ads (Wix App Extension)
 *
 * These routes are consumed by:
 *   1. Wix Dashboard pages (rendered in iframes, verified by wix.verify middleware)
 *   2. Wix site widgets (voter-facing video player)
 *   3. Wix webhooks (app installed/removed events, verified by signature)
 *
 * Security layers:
 *   - Webhook routes: Verified by HMAC signature (no user auth needed)
 *   - Voter routes: Bound by UUID (not sequential IDs) + rate-limited
 *   - Politician/Admin routes: Protected by wix.verify middleware
 */

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PoliticianController;
use App\Http\Controllers\Api\VoterController;
use App\Http\Controllers\Wix\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Endpoint
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'Dial4Dough API is running',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('api.health');

/*
|--------------------------------------------------------------------------
| Wix Webhooks (verified by HMAC signature — no auth middleware)
|--------------------------------------------------------------------------
*/
Route::post('/wix/webhooks', [WebhookController::class, 'handle'])
    ->name('api.wix.webhooks');

// Wix sends GET to verify the webhook endpoint is reachable
Route::get('/wix/webhooks', fn () => response()->json(['status' => 'ok']))
    ->name('api.wix.webhooks.verify');

/*
|--------------------------------------------------------------------------
| Versioned API — v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.v1.')->group(function () {

    /*
    |----------------------------------------------------------------------
    | Voter API (widget-facing — rate-limited, UUID-based)
    |----------------------------------------------------------------------
    */
    Route::middleware('throttle:60,1')->group(function () {
        // Registration (stricter rate limit)
        Route::post('/voters', [VoterController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('voters.store');

        // Voter profile & actions (identified by UUID — prevents enumeration)
        Route::prefix('/voters/{voter:uuid}')->name('voters.')->group(function () {
            Route::get('/', [VoterController::class, 'show'])->name('show');
            Route::get('/campaigns', [VoterController::class, 'availableCampaigns'])->name('campaigns');
            Route::post('/campaigns/{campaign:uuid}/watch', [VoterController::class, 'startView'])->name('watch');
            Route::get('/history', [VoterController::class, 'viewHistory'])->name('history');
            Route::get('/earnings', [VoterController::class, 'earnings'])->name('earnings');
            Route::get('/referrals', [VoterController::class, 'referrals'])->name('referrals');
        });

        // View session lifecycle (identified by UUID)
        Route::prefix('/sessions/{session:uuid}')->name('sessions.')->group(function () {
            Route::post('/progress', [VoterController::class, 'trackProgress'])->name('progress');
            Route::post('/complete', [VoterController::class, 'completeView'])->name('complete');
        });
    });

    /*
    |----------------------------------------------------------------------
    | Politician API (Wix Dashboard — requires instance verification)
    |----------------------------------------------------------------------
    */
    Route::middleware('wix.verify')->group(function () {
        Route::post('/politicians', [PoliticianController::class, 'store'])->name('politicians.store');

        Route::prefix('/politicians/{politician:uuid}')->name('politicians.')->group(function () {
            Route::get('/', [PoliticianController::class, 'show'])->name('show');
            Route::put('/', [PoliticianController::class, 'update'])->name('update');
            Route::post('/campaigns', [PoliticianController::class, 'createCampaign'])->name('campaigns.store');
            Route::get('/campaigns', [PoliticianController::class, 'campaigns'])->name('campaigns.index');
            Route::get('/campaigns/{campaign:uuid}', [PoliticianController::class, 'campaignShow'])->name('campaigns.show');
        });

        /*
        |------------------------------------------------------------------
        | Admin API (Wix Dashboard — requires instance verification)
        |------------------------------------------------------------------
        */
        Route::prefix('/admin')->name('admin.')->group(function () {
            Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
            Route::get('/campaigns/pending', [AdminController::class, 'pendingCampaigns'])->name('campaigns.pending');
            Route::post('/campaigns/{campaign:uuid}/approve', [AdminController::class, 'approveCampaign'])->name('campaigns.approve');
            Route::post('/campaigns/{campaign:uuid}/reject', [AdminController::class, 'rejectCampaign'])->name('campaigns.reject');
            Route::post('/payouts/process', [AdminController::class, 'processBatchPayouts'])->name('payouts.process');
            Route::get('/voters/flagged', [AdminController::class, 'flaggedVoters'])->name('voters.flagged');
            Route::post('/voters/{voter:uuid}/clear-flag', [AdminController::class, 'clearFraudFlag'])->name('voters.clear-flag');
        });
    });
});
