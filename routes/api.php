<?php

/**
 * API Routes for U9itus – Political Loyalty Ads (Standalone)
 *
 * These routes are consumed by:
 *   1. Dashboard pages (authenticated via Sanctum)
 *   2. Voter-facing video player widget
 *   3. Stripe webhooks
 *
 * Security layers:
 *   - Webhook routes: Verified by signature (no user auth needed)
 *   - Voter routes: Bound by UUID (not sequential IDs) + rate-limited
 *   - Politician/Admin routes: Protected by auth:sanctum middleware
 */

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\BillingController;
use App\Http\Controllers\Api\OfficeProfileController;
use App\Http\Controllers\Api\PayPalWebhookController;
use App\Http\Controllers\Api\PoliticianController;
use App\Http\Controllers\Api\VoterController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnboardingHandoffEventController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Health Check Endpoint
|--------------------------------------------------------------------------
*/
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'message' => 'U9itus API is running',
        'timestamp' => now()->toIso8601String(),
    ]);
})->name('api.health');

/*
|--------------------------------------------------------------------------
| Stripe Webhooks (minimal handler)
|--------------------------------------------------------------------------
*/
Route::post('/stripe/webhooks', [StripeWebhookController::class, 'handle'])
    ->name('api.stripe.webhooks');

Route::post('/paypal/webhooks', [PayPalWebhookController::class, 'handle'])
    ->name('api.paypal.webhooks');

/*
|--------------------------------------------------------------------------
| Versioned API — v1
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.v1.')->group(function () {

    // Dashboard notification endpoints use the web session cookie.
    // Keep them outside stateless API auth middleware to avoid 401s
    // when called from first-party dashboard pages on Railway.
    Route::middleware(['web', 'auth'])->prefix('/notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
        Route::post('/{id}/mark-as-read', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
        Route::post('/mark-all-as-read', [NotificationController::class, 'markAllAsRead'])->name('mark-all-as-read');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
        Route::delete('/delete-all-read', [NotificationController::class, 'deleteAllRead'])->name('delete-all-read');
    });

    Route::middleware(['web', 'auth'])->prefix('/onboarding-handoff-events')->name('onboarding-handoff-events.')->group(function () {
        Route::post('/', [OnboardingHandoffEventController::class, 'store'])->name('store');
    });

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

        // Public civic education — office profile for voter info popup (no auth)
        Route::get('/politicians/{politician:uuid}/office-profile', [OfficeProfileController::class, 'show'])
            ->name('politicians.office-profile');

        // Voter profile & actions (identified by UUID — prevents enumeration)
        Route::prefix('/voters/{voter:uuid}')->name('voters.')->group(function () {
            Route::get('/', [VoterController::class, 'show'])->name('show');
            Route::get('/campaigns', [VoterController::class, 'availableCampaigns'])->name('campaigns');
            Route::post('/campaigns/{campaign:uuid}/watch', [VoterController::class, 'startView'])->name('watch')->withoutScopedBindings();
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
    | Politician API (authenticated via Sanctum)
    |----------------------------------------------------------------------
    */
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/politicians', [PoliticianController::class, 'store'])->name('politicians.store');

        Route::prefix('/politicians/{politician:uuid}')->name('politicians.')->group(function () {
            Route::get('/', [PoliticianController::class, 'show'])->name('show');
            Route::put('/', [PoliticianController::class, 'update'])->name('update');
            Route::post('/campaigns', [PoliticianController::class, 'createCampaign'])->name('campaigns.store');
            Route::get('/campaigns', [PoliticianController::class, 'campaigns'])->name('campaigns.index');
            Route::get('/campaigns/{campaign:uuid}', [PoliticianController::class, 'campaignShow'])->name('campaigns.show');
            Route::post('/campaigns/{campaign:uuid}/pause', [PoliticianController::class, 'pauseCampaign'])->name('campaigns.pause');
            Route::post('/campaigns/{campaign:uuid}/resume', [PoliticianController::class, 'resumeCampaign'])->name('campaigns.resume');

            // Billing endpoints for politician
            Route::get('/billing/balance', [\App\Http\Controllers\Api\BillingController::class, 'balance'])->name('billing.balance');
            Route::post('/billing/purchase', [\App\Http\Controllers\Api\BillingController::class, 'purchase'])->name('billing.purchase');
        });

        /*
        |------------------------------------------------------------------
        | Admin API (authenticated via Sanctum)
        |------------------------------------------------------------------
        */
        Route::prefix('/admin')->name('admin.')->group(function () {
            Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
            Route::get('/campaigns/pending', [AdminController::class, 'pendingCampaigns'])->name('campaigns.pending');
            Route::post('/campaigns/{campaign:uuid}/approve', [AdminController::class, 'approveCampaign'])->name('campaigns.approve');
            Route::post('/campaigns/{campaign:uuid}/reject', [AdminController::class, 'rejectCampaign'])->name('campaigns.reject');
            Route::post('/campaigns/{campaign:uuid}/stop', [AdminController::class, 'stopCampaign'])->name('campaigns.stop');
            Route::post('/campaigns/{campaign:uuid}/reactivate', [AdminController::class, 'reactivateCampaign'])->name('campaigns.reactivate');
            Route::post('/payouts/process', [AdminController::class, 'processBatchPayouts'])->name('payouts.process');
            Route::get('/voters/flagged', [AdminController::class, 'flaggedVoters'])->name('voters.flagged');
            Route::post('/voters/{voter:uuid}/clear-flag', [AdminController::class, 'clearFraudFlag'])->name('voters.clear-flag');
        });
    });
});
