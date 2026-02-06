<?php

/**
 * API Routes for Dial4Dough – Political Loyalty Ads (Wix App Extension)
 *
 * These routes are consumed by:
 *   1. Wix Dashboard pages (rendered in iframes)
 *   2. Wix site widgets (voter-facing video player)
 *   3. Wix webhooks (app installed/removed events)
 */

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\PoliticianController;
use App\Http\Controllers\Api\VoterController;
use App\Http\Controllers\Wix\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Wix Webhooks (no auth — verified by signature)
|--------------------------------------------------------------------------
*/
Route::post('/wix/webhooks', [WebhookController::class, 'handle'])
    ->name('api.wix.webhooks');

/*
|--------------------------------------------------------------------------
| Public API (used by Wix widget on the site — voters)
|--------------------------------------------------------------------------
*/
Route::prefix('v1')->name('api.v1.')->group(function () {

    // Voter registration
    Route::post('/voters', [VoterController::class, 'store'])->name('voters.store');

    // Voter profile & actions (identified by voter ID)
    Route::prefix('/voters/{voter}')->name('voters.')->group(function () {
        Route::get('/', [VoterController::class, 'show'])->name('show');
        Route::get('/campaigns', [VoterController::class, 'availableCampaigns'])->name('campaigns');
        Route::post('/campaigns/{campaign}/watch', [VoterController::class, 'startView'])->name('watch');
        Route::get('/history', [VoterController::class, 'viewHistory'])->name('history');
        Route::get('/earnings', [VoterController::class, 'earnings'])->name('earnings');
        Route::get('/referrals', [VoterController::class, 'referrals'])->name('referrals');
    });

    // View session lifecycle
    Route::prefix('/sessions/{session}')->name('sessions.')->group(function () {
        Route::post('/progress', [VoterController::class, 'trackProgress'])->name('progress');
        Route::post('/complete', [VoterController::class, 'completeView'])->name('complete');
    });

    /*
    |----------------------------------------------------------------------
    | Politician API (used by Wix Dashboard pages)
    |----------------------------------------------------------------------
    */
    Route::post('/politicians', [PoliticianController::class, 'store'])->name('politicians.store');

    Route::prefix('/politicians/{politician}')->name('politicians.')->group(function () {
        Route::get('/', [PoliticianController::class, 'show'])->name('show');
        Route::put('/', [PoliticianController::class, 'update'])->name('update');
        Route::post('/campaigns', [PoliticianController::class, 'createCampaign'])->name('campaigns.store');
        Route::get('/campaigns', [PoliticianController::class, 'campaigns'])->name('campaigns.index');
        Route::get('/campaigns/{campaign}', [PoliticianController::class, 'campaignShow'])->name('campaigns.show');
    });

    /*
    |----------------------------------------------------------------------
    | Admin API
    |----------------------------------------------------------------------
    */
    Route::prefix('/admin')->name('admin.')->group(function () {
        Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
        Route::get('/campaigns/pending', [AdminController::class, 'pendingCampaigns'])->name('campaigns.pending');
        Route::post('/campaigns/{campaign}/approve', [AdminController::class, 'approveCampaign'])->name('campaigns.approve');
        Route::post('/campaigns/{campaign}/reject', [AdminController::class, 'rejectCampaign'])->name('campaigns.reject');
        Route::post('/payouts/process', [AdminController::class, 'processBatchPayouts'])->name('payouts.process');
        Route::get('/voters/flagged', [AdminController::class, 'flaggedVoters'])->name('voters.flagged');
        Route::post('/voters/{voter}/clear-flag', [AdminController::class, 'clearFraudFlag'])->name('voters.clear-flag');
    });
});
