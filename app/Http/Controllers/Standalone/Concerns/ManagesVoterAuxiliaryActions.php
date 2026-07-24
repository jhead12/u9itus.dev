<?php

namespace App\Http\Controllers\Standalone\Concerns;

use App\Enums\CampaignStatus;
use App\Enums\ViewPaymentStatus;
use App\Exceptions\StripeConnectException;
use App\Models\AdViewToken;
use App\Models\PoliticalCampaign;
use App\Models\ReferralVisit;
use App\Models\CitizenViewSession;
use App\Models\Voter;
use App\Models\VoterWatchReport;
use App\Models\ViewSession;
use App\Services\PlatformSettingsService;
use App\Services\StripeConnectService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ManagesVoterAuxiliaryActions
{
    // ── Dashboard ────────────────────────────────────────────

    /**
     * If the voter has started Stripe Connect (stripe_account_id is set) but the
     * account is not yet active in our DB, do a live Stripe API check and sync
     * the result. This handles the window between the voter returning from Stripe
     * Connect onboarding and the asynchronous account.updated webhook arriving.
     *
     * Only calls the Stripe API when necessary (account_id present, status not active).
     * No-op when Stripe is not configured or the voter has no account yet.
     */
    private function syncStripeAccountIfPending(Voter $voter): void
    {
        if (empty($voter->stripe_account_id)) {
            return;
        }

        if ($voter->stripe_account_status === 'active') {
            return;
        }

        try {
            $stripeConnect = app(StripeConnectService::class);
            if ($stripeConnect->isConfigured()) {
                $stripeConnect->getAccountStatus($voter);
                $voter->refresh(); // Reload after the sync updates the row.
            }
        } catch (\Throwable $e) {
            // Best-effort — never block page load if Stripe is unreachable.
            Log::warning('StripeConnect status sync failed on page load', [
                'voter_id'         => $voter->id,
                'stripe_account_id' => $voter->stripe_account_id,
                'error'            => $e->getMessage(),
            ]);
        }
    }

    public function dashboard()
    {
        $voter   = $this->resolveVoter()->loadMissing('user');
        $this->syncStripeAccountIfPending($voter);
        $summary = $this->viewService->voterEarningsSummary($voter);

        // Surface watchable campaigns directly on dashboard so voters can start earning immediately.
        $excludedCampaignIds = $this->excludedCampaignIdsForVoter($voter);

        $voterPrefs = $voter->preferred_governance_levels ?? [];

        $availableCampaignsQuery = \App\Models\PoliticalCampaign::needingViews()
            ->with('politician:id,full_name,political_office,governance_level,profile_photo_url,verified_official,slug,page_published')
            ->whereNotIn('id', $excludedCampaignIds);

        if (! empty($voterPrefs)) {
            $availableCampaignsQuery->whereIn('governance_level', $voterPrefs);
        }

        if ($voter->state) {
            $availableCampaignsQuery->where(function ($q) use ($voter) {
                $q->whereNull('target_states')
                  ->orWhereJsonContains('target_states', $voter->state);
            });
        }

        $availableCampaignsCount = (clone $availableCampaignsQuery)->count();
        $availableCampaigns = $availableCampaignsQuery
            ->orderByDesc('revenue_per_view')
            ->orderByDesc('updated_at')
            ->take(6)
            ->get();

        $recentSessions = $voter->viewSessions()
            ->with('campaign.politician')
            ->latest()
            ->take(10)
            ->get();

        // Get active promotions relevant to voters
        $activePromotions = \App\Models\PlatformSetting::active()
            ->whereNotNull('effective_until')
            ->whereIn('category', ['pricing', 'referral'])
            ->orderBy('effective_until')
            ->get();

        // Local candidate news — cached per state (15 min) so the DB is hit at most once
        // per state per cache window. Falls back to most recent global articles if the
        // voter has no state set.
        $newsCacheKey = 'voter-dashboard-news-' . ($voter->state ?: 'all');
        $candidateNews = Cache::remember($newsCacheKey, now()->addMinutes(15), function () use ($voter) {
            return \App\Models\CandidateNewsArticle::query()
                ->with('politician:id,full_name,slug')
                ->when($voter->state, fn ($q) =>
                    $q->whereHas('politician', fn ($p) => $p->where('state', $voter->state))
                )
                ->orderByDesc('published_at')
                ->take(3)
                ->get();
        });

        // Real primary/general election dates for the voter's state, synced
        // from Vote Smart (php artisan elections:sync-dates). Same per-state
        // cache pattern as $candidateNews above.
        $electionDates = $voter->state
            ? Cache::remember(
                'voter-dashboard-election-dates-' . $voter->state,
                now()->addHours(6),
                fn () => \App\Models\StateElectionDate::upcomingForState($voter->state)
            )
            : [];

        return view('standalone.voter.dashboard', [
            'user'            => Auth::user(),
            'voter'           => $voter,
            'summary'         => $summary,
            'availableCampaigns' => $availableCampaigns,
            'availableCampaignsCount' => $availableCampaignsCount,
            'recentSessions'  => $recentSessions,
            'activePromotions' => $activePromotions,
            'candidateNews'   => $candidateNews,
            'electionDates'   => $electionDates,
            'needsAuthenticUserVerifierMigration' => $voter->needsAuthenticUserVerifierMigration(),
        ]);
    }

    public function watchQuestions(string $token)
    {
        $adToken = AdViewToken::where('token', $token)
            ->with('campaign.politician', 'voter')
            ->firstOrFail();

        $voter = $this->resolveVoter();
        abort_unless((int) $adToken->voter_id === (int) $voter->id, 403);

        $campaign = $adToken->campaign;
        abort_unless($campaign, 404);

        $reportsTable = (new VoterWatchReport())->getTable();
        $hasPublicBoardColumns = Schema::hasColumns($reportsTable, [
            'public_visibility',
            'is_public_board',
            'campaign_replied_at',
            'published_at',
        ]);

        $questionsQuery = VoterWatchReport::query()
            ->messages()
            ->where('campaign_id', $campaign->id);

        if ($hasPublicBoardColumns) {
            $questionsQuery
                ->where(function ($query) {
                    $query->where(function ($approved) {
                        $approved->where('public_visibility', 'approved')
                            ->where('is_public_board', true);
                    })->orWhere(function ($legacy) {
                        $legacy->where('status', 'resolved')
                            ->whereNotNull('admin_notes');
                    });
                })
                ->orderByDesc('campaign_replied_at')
                ->orderByDesc('published_at');
        } else {
            $questionsQuery
                ->where('status', 'resolved')
                ->whereNotNull('admin_notes');
        }

        $questions = $questionsQuery
            ->orderByDesc('updated_at')
            ->paginate(12);

        return view('standalone.voter.watch-questions', compact('adToken', 'campaign', 'questions'));
    }

    /**
     * POST /voter/watch/{token}/start
     * Mark session as started; returns JSON { session_id, status }.
     */
    public function startWatching(Request $request, string $token)
    {
        $adToken = AdViewToken::where('token', $token)
            ->with('campaign', 'voter')
            ->first();

        if (! $adToken || ! $adToken->isValid()) {
            return response()->json(['error' => 'Token is invalid or expired.'], 422);
        }

        $voter    = $adToken->voter ?? $this->resolveVoter();
        $campaign = $adToken->campaign;

        try {
            // Consume the token before creating the session (prevents race-condition double-start)
            $adToken->markAsUsed($request->ip(), $request->userAgent());

            $session = $this->viewService->assignView($campaign, $voter, $request);
            $this->viewService->startView($session);

            return response()->json([
                'session_id' => $session->uuid,
                'status'     => 'started',
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Watch start blocked', ['token' => $token, 'reason' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 403);
        }
    }

    // ── Earnings ─────────────────────────────────────────────

    public function earnings()
    {
        $voter   = $this->resolveVoter()->loadMissing('user');
        $this->syncStripeAccountIfPending($voter);
        $summary = $this->viewService->voterEarningsSummary($voter);

        $sessions = $voter->viewSessions()
            ->with('campaign.politician')
            ->where('status', 'completed')
            ->latest('completed_at')
            ->paginate(15);

        return view('standalone.voter.earnings', [
            'voter' => $voter,
            'summary' => $summary,
            'sessions' => $sessions,
            'needsAuthenticUserVerifierMigration' => $voter->needsAuthenticUserVerifierMigration(),
        ]);
    }

    public function earningsHistory()
    {
        $voter    = $this->resolveVoter();
        $sessions = $voter->viewSessions()
            ->with('campaign.politician')
            ->latest()
            ->paginate(25);

        return view('standalone.voter.earnings-history', compact('voter', 'sessions'));
    }

    public function requestPayout()
    {
        $voter     = $this->resolveVoter();
        $user = Auth::user();
        $minPayout = (float) PlatformSettingsService::get('min_payout_amount', null, 5.00);

        $idmeConfigured = (string) config('services.idme.client_id', '') !== ''
            && (string) config('services.idme.client_secret', '') !== '';

        if ($idmeConfigured && $user->idme_verified_at === null) {
            return back()->withErrors([
                'payout' => 'Please complete Id.me verification before requesting payouts.',
            ])->with('idme_verification_url', route('verification.idme.redirect'));
        }

        if ((float) $voter->pending_earnings < $minPayout) {
            return back()->withErrors([
                'payout' => "Minimum payout is \${$minPayout}. You have \${$voter->pending_earnings} pending.",
            ]);
        }

        $payoutAmount = (float) $voter->pending_earnings;
        $selectedProcessor = match ((string) ($voter->payment_method ?? '')) {
            'paypal' => 'paypal',
            'cashapp' => 'cashapp',
            default => 'wallet',
        };

        // Ensure payout-eligible completed sessions are marked approved and carry
        // the voter's latest processor preference for downstream payout routing.
        // Covers both political (ViewSession) and citizen (CitizenViewSession)
        // campaigns — previously citizen earnings accrued in pending_earnings but
        // were never queued for settlement, leaving them stranded.
        $updated = DB::transaction(function () use ($voter, $selectedProcessor): int {
            $political = ViewSession::where('voter_id', $voter->id)
                ->where('status', \App\Enums\ViewSessionStatus::Completed)
                ->whereIn('payment_status', [
                    ViewPaymentStatus::Pending,
                    ViewPaymentStatus::Approved,
                ])
                ->where('voter_payout_amount', '>', 0)
                ->update([
                    'payment_status' => ViewPaymentStatus::Approved,
                    'processor_selected' => $selectedProcessor,
                ]);

            $citizen = CitizenViewSession::where('voter_id', $voter->id)
                ->where('status', \App\Enums\ViewSessionStatus::Completed)
                ->whereIn('payment_status', [
                    ViewPaymentStatus::Pending,
                    ViewPaymentStatus::Approved,
                ])
                ->where('voter_payout_amount', '>', 0)
                ->update([
                    'payment_status' => ViewPaymentStatus::Approved,
                    'processor_selected' => $selectedProcessor,
                ]);

            return $political + $citizen;
        });

        Log::info('Payout requested', [
            'voter_id'        => $voter->id,
            'amount'          => $payoutAmount,
            'method'          => $voter->payment_method,
            'sessions_queued' => $updated,
        ]);

        return back()->with('success', 'Payout request received! Processing within 1–2 business days.');
    }

    // ── Referrals ────────────────────────────────────────────

    public function referrals(Request $request)
    {
        $voter = $this->resolveVoter();

        // Auto-link when Early-bank redirects back via return_to with ?eb_member=<uuid>.
        // Handles the "EB member first, U9itus second" flow where earlybank_own_member_uuid
        // was never set because the user didn't go through the webhook registration path.
        if ($voter->earlybank_own_member_uuid === null) {
            $ebMember = $request->query('eb_member');
            if (is_string($ebMember) && \Illuminate\Support\Str::isUuid($ebMember)) {
                $voter->forceFill([
                    'earlybank_own_member_uuid' => $ebMember,
                    'earlybank_own_linked_at'   => now(),
                ])->save();
                $voter->refresh();
            }
        }

        // Voters referred by this voter
        $referrals = $voter->referrals()
            ->with('user:id,name,created_at')
            ->latest()
            ->get();

        // Politicians referred by this voter
        $referredPoliticians = \App\Models\Politician::where('referred_by_voter_id', $voter->id)
            ->with('user:id,name,created_at')
            ->latest()
            ->get();

        // Historical referral/procurement earnings — kept for backwards compatibility with
        // any admin reports that reference these. New commissions are handled by Early-bank.
        $referralEarnings     = collect();
        $procurementEarnings  = collect();
        $totalReferralEarnings    = (float) $voter->referralEarnings()->voterViews()->forActiveStripeMode()->sum('commission_amount');
        $totalProcurementEarnings = (float) $voter->referralEarnings()->procurements()->forActiveStripeMode()->sum('commission_amount');

        // Early-bank reported earnings — the actual money moving through the delegated
        // model. EB does not distinguish "view" vs "procurement" commissions on the same
        // payout.commission event, so we surface one combined commission total plus bonuses
        // rather than fabricate a split the ledger can't support.
        $ebCommissionTotal = (float) $voter->earlybankEarnings()
            ->forEventType(\App\Models\EarlyBankEarning::EVENT_PAYOUT_COMMISSION)
            ->sum('payout_amount');
        $ebBonusTotal = (float) $voter->earlybankEarnings()
            ->forEventType(\App\Models\EarlyBankEarning::EVENT_PAYOUT_BONUS)
            ->sum('payout_amount');

        $visitQuery = ReferralVisit::where('referrer_voter_id', $voter->id);
        $totalReferralVisits = (clone $visitQuery)->count();
        $uniqueReferralVisitors = (clone $visitQuery)
            ->whereNotNull('session_id')
            ->distinct('session_id')
            ->count('session_id');
        $referralConversions = (clone $visitQuery)->whereNotNull('converted_at')->count();
        $referralConversionRate = $totalReferralVisits > 0
            ? round(($referralConversions / $totalReferralVisits) * 100, 1)
            : 0.0;

        return view('standalone.voter.referrals', compact(
            'voter', 'referrals', 'referredPoliticians',
            'referralEarnings', 'procurementEarnings',
            'totalReferralEarnings', 'totalProcurementEarnings',
            'ebCommissionTotal', 'ebBonusTotal',
            'totalReferralVisits', 'uniqueReferralVisitors',
            'referralConversions', 'referralConversionRate'
        ));
    }

    public function getReferralLink()
    {
        $voter = $this->resolveVoter();
        $link  = url('/?ref=' . $voter->referral_code . '&target=voter');

        return response()->json(['link' => $link, 'code' => $voter->referral_code]);
    }

    // ── Early-bank SSO ────────────────────────────────────────

    /**
     * Generate a short-lived HMAC-signed token and redirect the voter directly
     * into their Early-bank dashboard without requiring a separate login.
     *
     * URL shape: {eb_url}/sso?member=<uuid>&ts=<unix>&sig=<hmac-sha256>
     * Early-bank validates: sig == HMAC-SHA256(member + '.' + ts, shared_secret)
     * and rejects tokens older than 5 minutes.
     *
     * Fallback: if the voter is not an EB member, or the secret is missing,
     * redirects to the Early-bank home page instead.
     */
    public function earlyBankSso()
    {
        $voter  = $this->resolveVoter();
        $ebUrl  = rtrim((string) config('services.earlybank.public_url', 'https://www.early-bank.com'), '/');
        $secret = (string) config('services.earlybank.webhook_secret', '');

        // No membership or no shared secret → fall back to plain EB home.
        if (empty($voter->earlybank_own_member_uuid) || $secret === '') {
            return redirect()->away($ebUrl);
        }

        $memberUuid = (string) $voter->earlybank_own_member_uuid;
        $ts         = (string) time();
        $sig        = hash_hmac('sha256', $memberUuid . '.' . $ts, $secret);

        $ssoUrl = $ebUrl . '/sso?' . http_build_query([
            'member' => $memberUuid,
            'ts'     => $ts,
            'sig'    => $sig,
        ]);

        return redirect()->away($ssoUrl);
    }

    // ── Preferences ──────────────────────────────────────────

    public function preferences()
    {
        $voter = $this->resolveVoter();
        return view('standalone.voter.preferences', compact('voter'));
    }

    public function updatePreferences(Request $request)
    {
        $validated = $request->validate([
            'payment_method'                => 'nullable|in:paypal,cashapp,stripe',
            'paypal_email'                  => 'nullable|email|max:255',
            'cashapp_tag'                   => 'nullable|string|max:100',
            'preferred_governance_levels'   => 'nullable|array',
            'preferred_governance_levels.*' => 'string|max:50',
        ]);

        $voter = $this->resolveVoter();
        $voter->update(array_filter($validated, fn ($v) => ! is_null($v)));

        return back()->with('success', 'Preferences updated successfully.');
    }

    // ── Profile ──────────────────────────────────────────────

    public function profile()
    {
        $voter = $this->resolveVoter()->loadMissing('user');
        return view('standalone.voter.profile', [
            'user'  => Auth::user(),
            'voter' => $voter,
            'needsAuthenticUserVerifierMigration' => $voter->needsAuthenticUserVerifierMigration(),
        ]);
    }

    /**
     * Redirect voter to Stripe Connect onboarding for the Authentic User Verifier flow.
     */
    public function startAuthenticUserVerifier(StripeConnectService $stripeConnect)
    {
        $voter = $this->resolveVoter();

        if (! $stripeConnect->isConfigured()) {
            return back()->withErrors([
                'payout' => 'Authentic User Verifier is temporarily unavailable. Please try again shortly.',
            ]);
        }

        // return_url: where Stripe sends the voter after they finish/abandon.
        // refresh_url: where Stripe sends them if the onboarding link expires
        // mid-flow — must restart the flow, not dump them on earnings.
        $returnUrl  = secure_url(route('voter.earnings', [], false));
        $refreshUrl = secure_url(route('voter.authentic-user-verifier.start', [], false));

        try {
            $link = $stripeConnect->createOnboardingLink($voter, $returnUrl, $refreshUrl);

            // NOTE: We intentionally do NOT set payment_method=stripe here.
            // The voter may abandon Stripe onboarding; payment_method should
            // only flip once the account.updated webhook reports activation.

            return redirect()->away((string) $link['url']);
        } catch (\Throwable $e) {
            $reference = (string) Str::ulid();

            // Classify any raw Stripe exception so the log always has a
            // consistent error_category and the user gets an actionable message.
            $classified = $e instanceof StripeConnectException
                ? $e
                : $stripeConnect->classifyStripeException($e);

            $stripeCtx     = $this->stripeErrorContext($e);
            $errorCategory = $classified->getMessage() !== $e->getMessage()
                ? 'stripe_classified'
                : 'stripe_other';

            // Derive a short category label from the raw Stripe error code when available.
            if (! empty($stripeCtx['stripe_error_code'])) {
                $errorCategory = 'stripe:' . $stripeCtx['stripe_error_code'];
            } elseif (! empty($stripeCtx['stripe_error_type'])) {
                $errorCategory = 'stripe:' . $stripeCtx['stripe_error_type'];
            }

            $context = [
                'reference'        => $reference,
                'error_category'   => $errorCategory,
                'voter_id'         => $voter->id,
                'exception'        => $e::class,
                'code'             => $e->getCode(),
                'error'            => $e->getMessage(),
                'user_message'     => $classified->getMessage(),
                'stripe_account_id' => $voter->stripe_account_id,
                'app_url'          => config('app.url'),
                'request_url'      => request()->fullUrl(),
                'return_url'       => $returnUrl,
                'refresh_url'      => $refreshUrl,
                ...$stripeCtx,
            ];

            Log::warning('Unable to start Authentic User Verifier onboarding', $context);
            Log::channel('stderr')->warning('Unable to start Authentic User Verifier onboarding', $context);

            report($e);

            return back()->withErrors([
                'payout' => $classified->getMessage() . ' Reference: ' . $reference,
            ]);
        }
    }

    /**
     * Redirect the voter into their Stripe Express Dashboard (same tab) to view
     * balance, payout history, and manage bank details.
     */
    public function openStripeDashboard(StripeConnectService $stripeConnect)
    {
        $voter = $this->resolveVoter();

        if (! $stripeConnect->isConfigured()) {
            return back()->withErrors([
                'payout' => 'Wallet management is temporarily unavailable. Please try again shortly.',
            ]);
        }

        try {
            $url = $stripeConnect->createLoginLink($voter);

            return redirect()->away($url);
        } catch (\Throwable $e) {
            $reference = (string) Str::ulid();

            $classified = $e instanceof StripeConnectException
                ? $e
                : $stripeConnect->classifyStripeException($e);

            $context = [
                'reference'         => $reference,
                'voter_id'          => $voter->id,
                'exception'         => $e::class,
                'code'              => $e->getCode(),
                'error'             => $e->getMessage(),
                'user_message'      => $classified->getMessage(),
                'stripe_account_id' => $voter->stripe_account_id,
                ...$this->stripeErrorContext($e),
            ];

            Log::warning('Unable to open Stripe Express Dashboard', $context);
            Log::channel('stderr')->warning('Unable to open Stripe Express Dashboard', $context);

            report($e);

            return back()->withErrors([
                'payout' => $classified->getMessage() . ' Reference: ' . $reference,
            ]);
        }
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'full_name'           => 'required|string|max:255',
            'phone'               => 'nullable|string|max:30',
            'state'               => 'nullable|string|max:2',
            'city'                => 'nullable|string|max:100',
            'zip_code'            => 'nullable|string|max:10',
            'is_registered_voter' => 'nullable|boolean',
        ]);

        // Allow explicit false (unchecked radio) to be stored
        if ($request->has('is_registered_voter')) {
            $isRegisteredVoterInput = (string) $request->input('is_registered_voter');
            if ($isRegisteredVoterInput === '1') {
                $validated['is_registered_voter'] = true;
            } elseif ($isRegisteredVoterInput === '0') {
                $validated['is_registered_voter'] = false;
            } else {
                $validated['is_registered_voter'] = null;
            }
        }

        $voter = $this->resolveVoter();

        // Address fields are locked after Stripe verification — strip them from
        // the update so a crafted request cannot alter the verified address.
        if ($voter->is_verified) {
            unset($validated['city'], $validated['state'], $validated['zip_code']);
        }

        $voter->update($validated);

        // Keep User name in sync
        Auth::user()->update(['name' => $validated['full_name']]);

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Upload a government-issued ID document for KYC (Know Your Customer) verification.
     *
     * Manual KYC uploads are permanently disabled for voters.
     * Voter identity verification is handled via Stripe identity verification
     * as part of the Stripe Connect payout onboarding flow.
     */
    public function uploadKycDocument()
    {
        return back()->withErrors([
            'kyc_document' => 'Manual document uploads are not available. Voter identity verification is handled automatically through Stripe when you set up payouts.',
        ]);
    }

    private function stripeErrorContext(\Throwable $e): array
    {
        if (! $e instanceof \Stripe\Exception\ApiErrorException) {
            return [];
        }

        $error = $e->getError();

        return [
            'stripe_http_status' => $e->getHttpStatus(),
            'stripe_request_id' => $e->getRequestId(),
            'stripe_error_type' => $error?->type,
            'stripe_error_code' => $error?->code,
            'stripe_error_param' => $error?->param,
            'stripe_error_decline_code' => $error?->decline_code,
        ];
    }

    private function resolveWatchError(?AdViewToken $adToken): ?array
    {
        $watchError = null;

        if (! $adToken) {
            $watchError = [
                'reason' => 'invalid',
                'message' => 'This viewing link is invalid.',
            ];
        } else {
            $adToken->checkExpiration();

            if (! $adToken->isValid()) {
                $watchError = [
                    'reason' => $adToken->is_used ? 'already_used' : 'expired',
                    'message' => $adToken->is_used
                        ? 'This link has already been used.'
                        : 'This link has expired.',
                ];
            }

            $campaign = $adToken->campaign;
            if ($watchError === null && (! $campaign || $campaign->status !== CampaignStatus::Active)) {
                $watchError = [
                    'reason' => 'unavailable',
                    'message' => 'This ad is no longer available.',
                ];
            }
        }

        return $watchError;
    }

    private function questionContainsBlockedTerms(string $text): bool
    {
        $terms = config('u9itus.q_and_a.moderation.blocked_terms', []);
        if (! is_array($terms) || empty($terms)) {
            return false;
        }

        $normalized = Str::lower(trim($text));
        $containsBlockedTerm = false;

        foreach ($terms as $term) {
            $needle = Str::lower(trim((string) $term));
            if ($needle !== '' && str_contains($normalized, $needle)) {
                $containsBlockedTerm = true;
                break;
            }
        }

        return $normalized === '' || $containsBlockedTerm;
    }

    private function detectReferencePlatform(?string $url): ?string
    {
        $value = trim((string) $url);
        $platform = null;

        if ($value !== '') {
            $host = strtolower((string) parse_url($value, PHP_URL_HOST));

            if ($host !== '') {
                if (str_contains($host, 'youtube.com') || str_contains($host, 'youtu.be')) {
                    $platform = 'youtube';
                } elseif (str_contains($host, 'facebook.com') || str_contains($host, 'fb.watch')) {
                    $platform = 'facebook';
                } elseif (str_contains($host, 'instagram.com')) {
                    $platform = 'instagram';
                } elseif (str_contains($host, 'tiktok.com')) {
                    $platform = 'tiktok';
                } elseif (str_contains($host, 'x.com') || str_contains($host, 'twitter.com')) {
                    $platform = 'twitter';
                }
            }
        }

        return $platform;
    }

    private function questionReferencesSchemaAvailable(): bool
    {
        return Schema::hasColumns('voter_watch_reports', [
            'reference_platform',
            'reference_url',
            'reference_start_seconds',
            'reference_end_seconds',
            'reference_note',
        ]);
    }

    private function recentPublicQuestionsForCampaign(PoliticalCampaign $campaign)
    {
        $reportsTable = (new VoterWatchReport())->getTable();
        $hasPublicBoardColumns = Schema::hasColumns($reportsTable, [
            'public_visibility',
            'is_public_board',
            'campaign_replied_at',
            'published_at',
        ]);

        $recentPublicQuestionsQuery = VoterWatchReport::query()
            ->messages()
            ->where('campaign_id', $campaign->id);

        if ($hasPublicBoardColumns) {
            $recentPublicQuestionsQuery
                ->where(function ($query) {
                    $query->where(function ($approved) {
                        $approved->where('public_visibility', 'approved')
                            ->where('is_public_board', true);
                    })->orWhere(function ($legacy) {
                        $legacy->where('status', 'resolved')
                            ->whereNotNull('admin_notes');
                    });
                })
                ->orderByDesc('campaign_replied_at')
                ->orderByDesc('published_at');
        } else {
            $recentPublicQuestionsQuery
                ->where('status', 'resolved')
                ->whereNotNull('admin_notes');
        }

        return $recentPublicQuestionsQuery
            ->orderByDesc('updated_at')
            ->take(2)
            ->get();
    }

    private function nextCampaignWithTokenForVoter(Voter $voter, PoliticalCampaign $campaign): array
    {
        $excludedCampaignIds = $this->excludedCampaignIdsForVoter($voter);
        $excludedCampaignIds[] = $campaign->id;

        $nextCampaign = PoliticalCampaign::needingViews()
            ->whereNotIn('id', $excludedCampaignIds)
            ->where('status', CampaignStatus::Active)
            ->orderByDesc('revenue_per_view')
            ->orderByDesc('updated_at')
            ->first();

        $nextAdToken = null;
        if ($nextCampaign) {
            $nextAdToken = AdViewToken::create([
                'voter_id'              => $voter->id,
                'political_campaign_id' => $nextCampaign->id,
                'notification_method'   => 'direct',
                'sent_to'               => $voter->email ?? 'direct',
                'sent_at'               => now(),
                'is_used'               => false,
                'is_expired'            => false,
                'expires_at'            => now()->addMinutes(30),
            ]);
        }

        return [$nextCampaign, $nextAdToken];
    }

    private function questionReferenceValidationState(array $validated): array
    {
        $referenceUrl = trim((string) ($validated['reference_url'] ?? ''));
        $hasReferenceInput = $referenceUrl !== ''
            || isset($validated['reference_start_seconds'])
            || isset($validated['reference_end_seconds'])
            || filled($validated['reference_note'] ?? null);
        $hasReferenceDetailsWithoutUrl = $referenceUrl === ''
            && (
                isset($validated['reference_start_seconds'])
                || isset($validated['reference_end_seconds'])
                || filled($validated['reference_note'] ?? null)
            );

        $validationError = null;
        $referencePlatform = null;
        $referenceSchemaAvailable = false;

        if ($this->questionContainsBlockedTerms($validated['body'])) {
            $validationError = 'Your question contains blocked language and could not be submitted.';
        } elseif ($hasReferenceDetailsWithoutUrl) {
            $validationError = 'Add a reference URL to include timestamps or a reference note.';
        } else {
            $referenceSchemaAvailable = $this->questionReferencesSchemaAvailable();
            if ($hasReferenceInput && ! $referenceSchemaAvailable) {
                $validationError = 'Advanced video references are temporarily unavailable. Please send the question without the reference details for now.';
            } elseif ($referenceUrl !== '') {
                $referencePlatform = $this->detectReferencePlatform($referenceUrl);
                if ($referencePlatform === null) {
                    $validationError = 'Unsupported reference source. Please use YouTube, Facebook, Instagram, TikTok, or X.';
                }
            }
        }

        return [$validationError, $referenceSchemaAvailable, $referencePlatform, $referenceUrl];
    }

    /**
     * View KYC document (self-service - voters can only view their own).
     */
    public function viewKycDocument()
    {
        $user = Auth::user();

        if (! $user->kyc_document_path) {
            abort(404, 'No KYC document found.');
        }

        // SEC-2: serve from the private `local` disk, not the public symlink.
        $path = Storage::disk('local')->path($user->kyc_document_path);

        if (! file_exists($path)) {
            abort(404, 'KYC document file not found on server.');
        }

        $mimeType = mime_content_type($path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    /**
     * Update voter password
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verify current password
        if (! Hash::check($request->current_password, $user->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        // Update password
        $user->update([
            'password' => Hash::make($request->new_password),
        ]);

        return back()->with('password_success', 'Password updated successfully.');
    }
}
