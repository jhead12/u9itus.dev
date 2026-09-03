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
use App\Http\Controllers\Api\EarlyBankController;
use App\Http\Controllers\Api\MapBusinessSearchController;
use App\Http\Controllers\Api\MapContentController;
use App\Http\Controllers\Api\MapDistrictConfigController;
use App\Http\Controllers\Api\MapDistrictNewsController;
use App\Http\Controllers\Api\MapGeocodeController;
use App\Http\Controllers\Api\MapInteractionController;
use App\Http\Controllers\Api\MapCandidateEconomyController;
use App\Http\Controllers\Api\MapCandidateMomentsController;
use App\Http\Controllers\Api\MapCandidateOverviewController;
use App\Http\Controllers\Api\MapCityCensusController;
use App\Http\Controllers\Api\MapRegionDemographicsController;
use App\Http\Controllers\Api\MapStateCandidatesController;
use App\Http\Controllers\Api\MapStateOverlaysController;
use App\Http\Controllers\Api\MapPoliticianSearchController;
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

    /*
    |----------------------------------------------------------------------
    | WebMCP tool backend (public, rate-limited)
    |----------------------------------------------------------------------
    | Consumed by the browser-side WebMCP tools in resources/js/webmcp/.
    | An AI agent browsing u9itus.dev calls these from a tool `execute()`.
    | Read endpoints are unauthenticated civic data; the lead-submit
    | endpoint only queues `pending` rows for human verification.
    | See doc/WEBMCP.md.
    */
    Route::prefix('/mcp')->name('mcp.')->group(function () {
        // Read endpoints — generous shared limit.
        Route::middleware('throttle:60,1')->group(function () {
            Route::get('/candidates', [\App\Http\Controllers\Api\WebMcpController::class, 'candidates'])->name('candidates');
            Route::get('/candidates/compare', [\App\Http\Controllers\Api\WebMcpController::class, 'compare'])->name('candidates.compare');
            Route::get('/candidates/{politician:uuid}', [\App\Http\Controllers\Api\WebMcpController::class, 'candidate'])->name('candidates.show');
            Route::get('/ballot-measures', [\App\Http\Controllers\Api\WebMcpController::class, 'ballotMeasures'])->name('ballot-measures');
            Route::get('/elections', [\App\Http\Controllers\Api\WebMcpController::class, 'elections'])->name('elections');
        });

        // Write endpoints — their own tighter limit (single throttle only; do
        // not nest, or the counters stack and the effective limit collapses).
        Route::post('/candidate-leads', [\App\Http\Controllers\Api\WebMcpController::class, 'submitLead'])
            ->middleware('throttle:10,1')
            ->name('candidate-leads.store');
        Route::post('/ballot-measures/watch', [\App\Http\Controllers\Api\WebMcpController::class, 'watchBallotMeasures'])
            ->middleware('throttle:10,1')
            ->name('ballot-measures.watch');
    });

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
    | Auth model: opaque bearer token (voter-token / voter.owns middleware),
    | not Sanctum — voters using only the widget may have no `users` row.
    | Full auth model comparison: doc/auth-architecture.md
    */
    // ── Public map data — no auth, rate-limited ──────────────────────────────
    Route::middleware('throttle:120,1')->group(function () {
        Route::get('/map/state-candidates', MapStateCandidatesController::class)
            ->name('map.state-candidates');

        // All-50-states-at-once summary for overview-zoom choropleth layers
        // (Party Control, Poverty Rate) — see MapStateOverlaysController's
        // docblock for why this is separate from state-candidates above.
        Route::get('/map/state-overlays', MapStateOverlaysController::class)
            ->name('map.state-overlays');

        // Live politician typeahead — powers the "Politicians" group in the
        // map's search palette (resources/js/map/ui/search.js).
        Route::get('/map/politician-search', MapPoliticianSearchController::class)
            ->name('map.politician-search');

        // Live business typeahead — powers the "Local Businesses" group in
        // the map's search palette (resources/js/map/ui/search.js).
        Route::get('/map/business-search', MapBusinessSearchController::class)
            ->name('map.business-search');

        // Region panel — cities + Census ACS demographics (poverty, education,
        // income) for the states within a region.
        Route::get('/map/region-demographics', MapRegionDemographicsController::class)
            ->name('map.region-demographics');

        // Single-city Census ACS demographics — powers the politician
        // drawer's city view (Economy tab). Dispatches a census-sync
        // workflow run when the requested city has no data yet.
        Route::get('/map/city-census', MapCityCensusController::class)
            ->name('map.city-census');

        Route::get('/map/candidate-overview', MapCandidateOverviewController::class)
            ->name('map.candidate-overview');

        Route::get('/map/candidate-economy', MapCandidateEconomyController::class)
            ->name('map.candidate-economy');

        Route::get('/map/candidate-moments', MapCandidateMomentsController::class)
            ->name('map.candidate-moments');

        // District boundary config — congress number, TIGERweb layer, CD field,
        // and party map derived from seated House members. Used by the 3D map to
        // render district overlays dynamically without a code deploy.
        Route::get('/map/district-config', MapDistrictConfigController::class)
            ->name('map.district-config');

        // Reverse geocode lat/lng → congressional district for the 3D map.
        Route::get('/map/geocode', MapGeocodeController::class)
            ->name('map.geocode');

        // Local election/civic-administration news (polling places, ballot
        // measures, redistricting) scoped to a clicked district's localities.
        Route::get('/map/district-news', MapDistrictNewsController::class)
            ->name('map.district-news');

        // Geo-tagged civic content (blog posts, later events) for the 3D map.
        Route::get('/map/content', MapContentController::class)
            ->name('map.content');

        // Anonymous map click analytics — fire-and-forget from the browser.
        // No auth required; IPs are SHA-256 hashed before storage.
        Route::post('/map/interaction', [MapInteractionController::class, 'store'])
            ->name('map.interaction');
    });

    Route::middleware('throttle:60,1')->group(function () {
        // Registration (stricter rate limit)
        Route::post('/voters', [VoterController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('voters.store');

        // Public civic education — office profile for voter info popup (no auth)
        Route::get('/politicians/{politician:uuid}/office-profile', [OfficeProfileController::class, 'show'])
            ->name('politicians.office-profile');

        // SEC-4: rotate the caller's voter API token (requires current token).
        // Defined before the {voter:uuid} prefix group so the static "token"
        // segment is never mistaken for a voter UUID.
        Route::post('/voters/token/rotate', [VoterController::class, 'rotateToken'])
            ->middleware('voter-token')
            ->name('voters.token.rotate');

        // Voter profile & actions (identified by UUID — prevents enumeration)
        // SEC-4: authenticated via voter bearer token; the token's voter must
        // match the {voter:uuid} route param (ownership enforced per route).
        Route::prefix('/voters/{voter:uuid}')
            ->middleware(['voter-token', 'voter.owns:voter'])
            ->name('voters.')
            ->group(function () {
                Route::get('/', [VoterController::class, 'show'])->name('show');
                Route::get('/campaigns', [VoterController::class, 'availableCampaigns'])->name('campaigns');
                Route::post('/campaigns/{campaign:uuid}/watch', [VoterController::class, 'startView'])->name('watch')->withoutScopedBindings();
                Route::get('/history', [VoterController::class, 'viewHistory'])->name('history');
                Route::get('/earnings', [VoterController::class, 'earnings'])->name('earnings');
                Route::get('/referrals', [VoterController::class, 'referrals'])->name('referrals');
                Route::post('/connect/onboard', [VoterController::class, 'connectOnboard'])->name('connect.onboard');
                Route::get('/connect/status', [VoterController::class, 'connectStatus'])->name('connect.status');
            });

        // View session lifecycle (identified by UUID)
        // SEC-4 / COR-5: authenticated via voter bearer token; the token's
        // voter must own the {session:uuid} (session.voter_id === token voter).
        Route::prefix('/sessions/{session:uuid}')
            ->middleware(['voter-token', 'voter.owns:session'])
            ->name('sessions.')
            ->group(function () {
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
        Route::prefix('/admin')->name('admin.')->middleware(['role:admin', 'admin.2fa'])->group(function () {
            Route::get('/analytics', [AdminController::class, 'analytics'])->name('analytics');
            Route::get('/campaigns/pending', [AdminController::class, 'pendingCampaigns'])->name('campaigns.pending');
            Route::post('/campaigns/{campaign:uuid}/approve', [AdminController::class, 'approveCampaign'])->name('campaigns.approve');
            Route::post('/campaigns/{campaign:uuid}/reject', [AdminController::class, 'rejectCampaign'])->name('campaigns.reject');
            Route::post('/campaigns/{campaign:uuid}/stop', [AdminController::class, 'stopCampaign'])->name('campaigns.stop');
            Route::post('/campaigns/{campaign:uuid}/reactivate', [AdminController::class, 'reactivateCampaign'])->name('campaigns.reactivate');
            Route::post('/payouts/process', [AdminController::class, 'processBatchPayouts'])->name('payouts.process');
            Route::get('/voters/flagged', [AdminController::class, 'flaggedVoters'])->name('voters.flagged');
            Route::post('/voters/{voter:uuid}/clear-flag', [AdminController::class, 'clearFraudFlag'])->name('voters.clear-flag');

            // ── Registration Security — IP blocking & rate limit audit ────────
            Route::prefix('/registration-security')->name('registration-security.')->group(function () {
                Route::get('/attempts', [AdminController::class, 'registrationAttempts'])->name('attempts');
                Route::get('/attempts/ip/{ip}', [AdminController::class, 'registrationAttemptsByIp'])->name('attempts.by-ip');
                Route::get('/ip-blocks', [AdminController::class, 'activeIpBlocks'])->name('ip-blocks');
                Route::post('/ip-blocks', [AdminController::class, 'blockIp'])->name('ip-blocks.store');
                Route::delete('/ip-blocks/{ip}', [AdminController::class, 'unblockIp'])->name('ip-blocks.destroy');
            });
        });
    });

    /*
    |----------------------------------------------------------------------
    | Early-bank.com Server-to-Server API
    |----------------------------------------------------------------------
    | Authenticated by a shared bearer token (EARLYBANK_API_TOKEN).
    | These endpoints are expected to be called only from the earlybank
    | sibling service over Railway's private network. Rate-limited as a
    | defense-in-depth measure.
    */
    Route::middleware(['earlybank.api', 'throttle:120,1'])
        ->prefix('/earlybank')
        ->name('earlybank.')
        ->group(function () {
            Route::post('/register-referral', [EarlyBankController::class, 'registerReferral'])
                ->name('register-referral');

            // Inbound webhook from earlybank.com: commissions, bonuses, member status.
            // Signature verification happens inside the controller so that a failed
            // signature still returns a structured 401 rather than a middleware abort.
            Route::post('/webhook', [EarlyBankController::class, 'webhook'])
                ->name('webhook');

            // Fired by earlybank.com when a U9itus user (voter or politician) joins
            // Early-bank as a paying member. Stores their own EB member UUID so U9itus
            // can surface their personal EB referral link in the referrals page.
            Route::post('/member-enrolled', [EarlyBankController::class, 'memberEnrolled'])
                ->name('member-enrolled');

            Route::get('/voter/{voter:uuid}/earnings', [EarlyBankController::class, 'voterEarnings'])
                ->name('voter.earnings');

            Route::get('/member/{member_id}/stats', [EarlyBankController::class, 'memberStats'])
                ->name('member.stats');

            // Resolves a voter UUID from their email address.
            // Used by the Early-bank DashboardController to link users who registered
            // on U9itus before creating their Early-bank account.
            Route::get('/voter-by-email', [EarlyBankController::class, 'voterByEmail'])
                ->name('voter-by-email');
        });
});
