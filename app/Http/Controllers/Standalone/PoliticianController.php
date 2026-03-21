<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCampaignRequest;
use App\Http\Requests\SaveCampaignDraftRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Services\StripePaymentService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\CampaignBillingService;

/**
 * Standalone Politician Controller
 * 
 * Handles politician-specific features in standalone mode:
 * - Campaign management
 * - Video uploads
 * - Analytics
 * - Billing
 */
class PoliticianController extends Controller
{
    /**
     * Active app payment mode derived from configured Stripe secret.
     */
    private function activePaymentMode(): ?string
    {
        $mode = app(StripePaymentService::class)->configuredMode();
        return in_array($mode, ['live', 'test'], true) ? $mode : null;
    }

    /**
     * Apply mode-aware filter to ledger/transaction style queries.
     */
    private function applyPaymentModeFilter($query, ?string $mode)
    {
        if (! $mode) {
            return $query;
        }

        return $query->where('metadata->payment_mode', $mode);
    }

    /**
     * Show the politician dashboard.
     */
    public function dashboard()
    {
        $user = Auth::user();
        $politician = $user->politician;

        if (! $politician) {
            return view('standalone.dashboard.index', ['user' => $user]);
        }

        // Load recent campaigns (eager load to avoid N+1)
        $recentCampaigns = $politician->campaigns()
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        // Compute credit balance from latest ledger entry
        $creditBalance = PoliticianCredit::where('politician_id', $politician->id)
            ->orderByDesc('created_at')
            ->value('balance_after') ?? 0.00;

        $stats = [
            'active_campaigns'  => $politician->campaigns()->where('status', 'active')->count(),
            'total_campaigns'   => $politician->campaigns()->count(),
            'total_views'       => $politician->total_views_received ?? 0,
            'total_spent'       => $politician->total_spent ?? 0.00,
            'credit_balance'    => $creditBalance,
        ];

        // Get active promotions relevant to politicians
        $activePromotions = \App\Models\PlatformSetting::active()
            ->whereNotNull('effective_until')
            ->whereIn('category', ['pricing', 'referral'])
            ->orderBy('effective_until')
            ->get();

        return view('standalone.politician.dashboard', [
            'user'             => $user,
            'politician'       => $politician,
            'recentCampaigns'  => $recentCampaigns,
            'stats'            => $stats,
            'activePromotions' => $activePromotions,
        ]);
    }

    // ─── Campaign CRUD ────────────────────────────────────────────────────────

    /** List all campaigns for this politician. */
    public function campaigns(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $query = $politician->campaigns()->orderByDesc('created_at');
        
        // Apply status filter if provided
        if ($statusFilter = $request->get('status')) {
            if ($statusFilter === 'draft') {
                $query->where('status', 'draft');
            } elseif ($statusFilter === 'active') {
                $query->whereIn('status', ['active', 'paused', 'scheduled']);
            } elseif ($statusFilter === 'completed') {
                $query->whereIn('status', ['completed', 'cancelled']);
            }
        }

        $campaigns = $query->paginate(12)->appends($request->query());

        return view('standalone.politician.campaigns.index', compact('campaigns', 'politician'));
    }

    /** Show campaign creation form. */
    public function createCampaign()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $revenuePerView = (float) PlatformSettingsService::get('revenue_per_view', null, 0.60);
        $creditBalance  = (float) ($politician->credit_balance ?? 0.00);
        $states = config('u9itus.us_states', []);
        $governanceLevels = config('u9itus.governance_levels', [
            'Federal' => 'Federal', 'State' => 'State', 'County' => 'County',
            'City' => 'City', 'School Board' => 'School Board',
        ]);

        return view('standalone.politician.campaigns.create', compact(
            'politician', 'revenuePerView', 'creditBalance', 'states', 'governanceLevels'
        ));
    }

    /** Store a new campaign. */
    public function storeCampaign(CreateCampaignRequest $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $data = $request->validated();
        $data['politician_id'] = $politician->id;
        $data['status']        = 'draft';
        $data['revenue_per_view'] = (float) PlatformSettingsService::get('revenue_per_view', null, 0.60);
        $data['voter_payout_per_view'] = (float) PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
        // Always recompute total_budget from views × rate (never trust form input)
        $data['total_budget'] = round((float)($data['total_views_requested'] ?? 0) * $data['revenue_per_view'], 2);

        // Set default media_duration if not provided (will be auto-detected from video later)
        if (empty($data['media_duration']) && $data['campaign_type'] === 'video') {
            $data['media_duration'] = config('u9itus.min_video_duration', 30);
        }

        $campaign = PoliticalCampaign::create($data);

        return redirect()
            ->route('politician.campaigns.show', $campaign)
            ->with('success', 'Campaign created! Upload a video and submit for review when ready.');
    }

    /** Save campaign as draft with partial data. */
    public function saveDraft(SaveCampaignDraftRequest $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $data = array_filter($request->validated(), fn($value) => $value !== null);
        
        // Set defaults for draft campaigns
        $data['politician_id'] = $politician->id;
        $data['status'] = 'draft';
        $data['revenue_per_view'] = (float) PlatformSettingsService::get('revenue_per_view', null, 0.60);
        $data['voter_payout_per_view'] = (float) PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
        
        // Set default media_duration if not provided
        if (empty($data['media_duration']) && ($data['campaign_type'] ?? '') === 'video') {
            $data['media_duration'] = config('u9itus.min_video_duration', 30);
        }
        
        // Ensure we have at least a title for the draft
        if (!isset($data['title']) || empty($data['title'])) {
            $data['title'] = 'Draft Campaign - ' . now()->format('M d, Y g:i A');
        }

        $campaign = PoliticalCampaign::create($data);

        return redirect()
            ->route('politician.campaigns.edit', $campaign)
            ->with('success', 'Draft saved! You can complete it anytime.');
    }

    /** Show campaign detail. */
    public function showCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $campaign->load('viewSessions');
        $completedViews = $campaign->views_completed ?? 0;
        $budgetUsed     = $campaign->amount_spent ?? 0;
        $budgetLeft     = ($campaign->total_budget ?? 0) - $budgetUsed;

        // Phase 14 — Repeat Viewing stats
        $uniqueVoters   = $campaign->viewSessions
            ->where('status', 'completed')
            ->unique('voter_id')
            ->count();
        $repeatViews    = max(0, $completedViews - $uniqueVoters);

        $creditBalance  = (float) ($politician->credit_balance ?? 0.00);

        return view('standalone.politician.campaigns.show', compact(
            'campaign', 'politician', 'completedViews', 'budgetUsed', 'budgetLeft',
            'uniqueVoters', 'repeatViews', 'creditBalance'
        ));
    }

    /** Show campaign edit form. */
    public function editCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused', 'scheduled']),
            403
        );

        $states = config('u9itus.us_states', []);
        $governanceLevels = config('u9itus.governance_levels', [
            'Federal' => 'Federal', 'State' => 'State', 'County' => 'County',
            'City' => 'City', 'School Board' => 'School Board',
        ]);

        return view('standalone.politician.campaigns.edit', compact(
            'campaign', 'politician', 'states', 'governanceLevels'
        ));
    }

    /** Update a campaign. */
    public function updateCampaign(UpdateCampaignRequest $request, PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused', 'scheduled']),
            403
        );

        $validated = $request->validated();
        $revenuePerView = (float) PlatformSettingsService::get('revenue_per_view', null, 0.60);
        // Always recompute total_budget from views × rate (never trust form input)
        $validated['total_budget'] = round(
            (float)($validated['total_views_requested'] ?? $campaign->total_views_requested)
            * $revenuePerView,
            2
        );

        $campaign->update($validated);

        return redirect()
            ->route('politician.campaigns.show', $campaign)
            ->with('success', 'Campaign updated successfully.');
    }

    /** Delete a campaign (draft/cancelled only). */
    public function destroyCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'cancelled']),
            403
        );

        $campaign->delete();

        return redirect()
            ->route('politician.campaigns.index')
            ->with('success', 'Campaign deleted.');
    }

    /** Pause an active campaign. */
    public function pauseCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'Campaign paused.');
    }

    /** Resume a paused campaign. */
    public function resumeCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $campaign->update(['status' => 'active']);

        return back()->with('success', 'Campaign resumed.');
    }

    // ─── Other pages ───────────────────────────────────────────────────────────

    /** Submit a draft campaign for admin approval. */
    public function submitForReview(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id,
            403
        );

        abort_unless(
            ($campaign->status?->value ?? $campaign->status) === 'draft',
            422,
            'Only draft campaigns can be submitted for review.'
        );

        abort_unless(
            $campaign->media_url || $campaign->live_feed_url,
            422,
            'Please upload a video or set a live stream URL before submitting.'
        );

        // Credit gate: politician must hold enough balance to cover the full campaign budget.
        $balance = (float) ($politician->credit_balance ?? 0.00);
        $budget  = (float) ($campaign->total_budget ?? 0.00);
        if ($balance < $budget) {
            $needed = number_format($budget - $balance, 2);
            return back()->withErrors([
                'credits' => "Insufficient credit balance. You need \${$needed} more to submit this campaign. Add credits and try again.",
            ]);
        }

        $campaign->update(['status' => 'pending_approval', 'approval_status' => 'pending']);

        return back()->with('success', 'Campaign submitted for review. You will be notified once approved.');
    }

    /** Handle video file upload for a campaign (local/S3). */
    public function uploadVideo(Request $request, PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused']),
            403
        );

        $maxMb  = config('u9itus.max_video_size_mb', 100);
        $minSec = config('u9itus.min_video_duration', 30);
        $maxSec = config('u9itus.max_video_duration', 300);

        $request->validate([
            'video' => [
                'required',
                'file',
                'mimetypes:video/mp4,video/quicktime,video/webm',
                'max:' . ($maxMb * 1024),
            ],
        ]);

        $file = $request->file('video');

        // ── Strict duration check via ffprobe (when available) ───────────────
        // ffprobe is included with ffmpeg; install: `apt install ffmpeg` / `brew install ffmpeg`
        $ffprobe = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');
        if ($ffprobe) {
            $tmpPath  = $file->getRealPath();
            $duration = (float) shell_exec(
                escapeshellcmd($ffprobe)
                . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
                . escapeshellarg($tmpPath)
                . ' 2>/dev/null'
            );

            if ($duration > 0) {
                if ($duration < $minSec) {
                    return back()->withErrors(['video' => "Video is too short ({$duration}s). Minimum is {$minSec} seconds."]);
                }
                if ($duration > $maxSec) {
                    $rounded = round($duration, 1);
                    return back()->withErrors(['video' => "Video is too long ({$rounded}s). Maximum is {$maxSec} seconds. Please trim your video and re-upload."]);
                }
                // Persist the detected duration on the campaign
                $campaign->media_duration = (int) round($duration);
            }
        }

        $disk = config('filesystems.default', 'local');

        // Delete old video if present
        if ($campaign->media_url) {
            $oldPath = parse_url($campaign->media_url, PHP_URL_PATH);
            Storage::disk($disk)->delete(ltrim($oldPath, '/'));
        }

        $path = $file->store("campaigns/{$campaign->id}/video", $disk);
        $url  = Storage::disk($disk)->url($path);

        $campaign->update(array_filter([
            'media_url'      => $url,
            'media_duration' => $campaign->media_duration,
        ], fn ($v) => $v !== null));

        return back()->with('success', 'Video uploaded successfully.');
    }

    /** Overall analytics for this politician (all campaigns). */
    public function analytics()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $campaigns = $politician->campaigns()
            ->withCount(['viewSessions as total_sessions'])
            ->orderByDesc('created_at')
            ->get();

        $totalViews    = $campaigns->sum('views_completed');
        $totalSpent    = $campaigns->sum('amount_spent');
        $totalBudget   = $campaigns->sum('total_budget');
        $activeCampaigns = $campaigns->where('status.value', 'active')
            ->merge($campaigns->whereStrict('status', 'active'))
            ->unique('id')
            ->count();

        $transactionsWithFeeSummary = CampaignTransaction::where('politician_id', $politician->id)
            ->where('transaction_type', 'charge')
            ->get()
            ->map(function (CampaignTransaction $tx): array {
                $metadata = is_array($tx->metadata) ? $tx->metadata : [];

                return [
                    'credits' => (float) ($metadata['credits_amount'] ?? 0),
                    'fee' => (float) ($metadata['stripe_fee'] ?? 0),
                    'gross' => (float) $tx->amount,
                ];
            });

        return view('standalone.politician.analytics', compact(
            'politician', 'campaigns',
            'totalViews', 'totalSpent', 'totalBudget', 'activeCampaigns', 'transactionsWithFeeSummary'
        ));
    }

    /** Per-campaign analytics detail. */
    public function campaignAnalytics(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $sessions = $campaign->viewSessions()
            ->orderByDesc('created_at')
            ->paginate(20);

        // Aggregate status breakdown
        $byStatus = $campaign->viewSessions()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $completedViews = $campaign->views_completed ?? 0;
        $budgetUsed     = $campaign->amount_spent ?? 0;
        $budgetLeft     = ($campaign->total_budget ?? 0) - $budgetUsed;

        return view('standalone.politician.analytics.campaign', compact(
            'campaign', 'politician', 'sessions', 'byStatus',
            'completedViews', 'budgetUsed', 'budgetLeft'
        ));
    }

    /** Billing overview: credit balance + transaction history. */
    public function billing()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $activePaymentMode = $this->activePaymentMode();

        $creditBalance = (float) $this->applyPaymentModeFilter(
            PoliticianCredit::where('politician_id', $politician->id),
            $activePaymentMode
        )->sum('amount');

        $credits = $this->applyPaymentModeFilter(
            PoliticianCredit::where('politician_id', $politician->id),
            $activePaymentMode
        )->orderByDesc('created_at')->paginate(15);

        $transactions = $this->applyPaymentModeFilter(
            CampaignTransaction::where('politician_id', $politician->id),
            $activePaymentMode
        )->orderByDesc('created_at')->paginate(15);

        return view('standalone.politician.billing', compact(
            'politician', 'creditBalance', 'credits', 'transactions', 'activePaymentMode'
        ));
    }

    /**
     * Add funds via Stripe — creates a PaymentIntent and returns the client_secret
     * as JSON so the billing page Stripe.js can complete the card payment in-browser.
     *
     * Expects: POST with JSON or form body { amount: number }
     * Returns: JSON { client_secret, publishable_key, amount }
     */
    public function addFunds(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:10000'],
        ]);

        /** @var \App\Services\CampaignBillingService $billing */
        $billing = app(\App\Services\CampaignBillingService::class);

        try {
            $intentData = $billing->createPurchaseIntent($politician, (float) $validated['amount'], [
                'description' => 'Credit top-up for politician #' . $politician->id,
            ]);
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }
            return back()->withErrors(['amount' => 'Payment service unavailable: ' . $e->getMessage()]);
        }

        // Always return JSON — the billing view submits via fetch() and handles the response.
        // return_url points to billing/confirm so the redirect immediately finalizes the
        // transaction and credits the politician without waiting for the Stripe webhook.
        return response()->json([
            'client_secret'      => $intentData['client_secret'],
            'payment_intent'     => $intentData['payment_intent_id'],
            'amount'             => $intentData['gross_amount'],      // total charged to card
            'credits_amount'     => $intentData['credits_amount'],    // credits added to account
            'stripe_fee'         => $intentData['stripe_fee'],        // 2.5% processing fee
            'stripe_fee_percent' => $intentData['stripe_fee_percent'],
            'publishable_key'    => config('services.stripe.public'),
            'return_url'         => route('politician.billing.confirm'),
        ]);
    }

    /**
     * Handle the Stripe redirect after a PaymentIntent completes.
     *
     * Stripe appends: ?payment_intent=pi_xxx&redirect_status=succeeded|failed|canceled
     * We immediately finalize the transaction and credit the politician so the balance
     * updates without waiting for the webhook.
     */
    public function confirmPayment(Request $request)
    {
        $piId           = $request->query('payment_intent');
        $redirectStatus = $request->query('redirect_status');

        if ($piId && $redirectStatus === 'succeeded') {
            $finalized = false;
            try {
                /** @var \App\Services\CampaignBillingService $billing */
                $billing = app(\App\Services\CampaignBillingService::class);
                $billing->finalizePaymentIntent($piId);
                $finalized = true;
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('confirmPayment finalizePaymentIntent failed: ' . $e->getMessage());
            }

            return redirect()->route('politician.billing')
                ->with($finalized ? 'payment_confirmed' : 'payment_failed', true);
        }

        if (in_array($redirectStatus, ['failed', 'canceled'])) {
            return redirect()->route('politician.billing')
                ->with('payment_failed', true);
        }

        return redirect()->route('politician.billing');
    }

    /** Invoice / transaction history page. */
    public function invoices()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $activePaymentMode = $this->activePaymentMode();

        $transactions = $this->applyPaymentModeFilter(
            CampaignTransaction::where('politician_id', $politician->id),
            $activePaymentMode
        )->orderByDesc('created_at')
         ->paginate(25);

        return view('standalone.politician.invoices', compact('politician', 'transactions', 'activePaymentMode'));
    }

    /**
     * Re-send receipt email for a past succeeded credit-purchase transaction.
     */
    public function sendReceipt(CampaignTransaction $transaction, CampaignBillingService $billingService)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $transaction->politician_id === (int) $politician->id, 403);

        $activePaymentMode = $this->activePaymentMode();
        $txMode = $transaction->metadata['payment_mode'] ?? null;
        if ($activePaymentMode && $txMode && $txMode !== $activePaymentMode) {
            return back()->withErrors(['receipt' => 'Transaction is outside the active payment mode view.']);
        }

        if ($transaction->transaction_type !== 'charge' || $transaction->status !== 'succeeded') {
            return back()->withErrors(['receipt' => 'Receipts are available only for succeeded credit purchases.']);
        }

        $sent = $billingService->sendCreditsPurchaseReceiptForTransaction($transaction);

        if (! $sent) {
            return back()->withErrors(['receipt' => 'Unable to send receipt right now. Please try again later.']);
        }

        return back()->with('success', 'Receipt email sent successfully.');
    }

    /**
     * Upload a government-issued ID document for KYC (Know Your Customer) verification.
     *
     * Accepts jpg/jpeg/png/pdf up to 5 MB. Stores on the `public` disk under
     * `kyc/{user_id}/document.{ext}` and resets kyc_status to 'pending' so
     * the admin is prompted to review the new document.
     */
    public function uploadKycDocument(Request $request)
    {
        $request->validate([
            'kyc_document' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:5120', // 5 MB
            ],
        ]);

        $user = Auth::user();
        $file = $request->file('kyc_document');

        // Delete old document if one exists
        if ($user->kyc_document_path) {
            Storage::disk('public')->delete($user->kyc_document_path);
        }

        $ext  = $file->getClientOriginalExtension();
        $path = $file->storeAs("kyc/{$user->id}", "document.{$ext}", 'public');

        // Save path and reset KYC status to pending so admin reviews the new doc
        $user->update([
            'kyc_document_path'    => $path,
            'kyc_status'           => 'pending',
            'kyc_reviewed_at'      => null,
            'kyc_reviewer_id'      => null,
            'kyc_rejection_reason' => null,
        ]);

        return back()->with('kyc_success', 'Document uploaded successfully. Your identity is now pending review.');
    }

    /**
     * View KYC document (self-service - politicians can only view their own).
     */
    public function viewKycDocument()
    {
        $user = Auth::user();

        if (!$user->kyc_document_path) {
            abort(404, 'No KYC document found.');
        }

        $path = storage_path('app/public/' . $user->kyc_document_path);

        if (!file_exists($path)) {
            abort(404, 'KYC document file not found on server.');
        }

        $mimeType = mime_content_type($path);
        
        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="' . basename($path) . '"',
        ]);
    }

    /** Show profile edit form. */
    public function profile()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $governanceLevels = config('u9itus.governance_levels', []);
        $states = config('u9itus.us_states', []);

        return view('standalone.politician.profile', compact('politician', 'governanceLevels', 'states'));
    }

    /** Save profile changes. */
    public function updateProfile(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'full_name'              => 'required|string|max:255',
            'political_office'       => 'nullable|string|max:255',
            'governance_level'       => 'nullable|string|max:100',
            'district'               => 'nullable|string|max:100',
            'party_affiliation'      => 'nullable|string|max:100',
            'state'                  => 'nullable|string|max:2',
            'city'                   => 'nullable|string|max:100',
            'website_url'            => 'nullable|url|max:255',
            'bio'                    => 'nullable|string|max:2000',
            'video_links'            => 'nullable|array|max:20',
            'video_links.*.url'      => 'required|url|max:500',
            'video_links.*.title'    => 'nullable|string|max:200',
        ]);

        // Filter out any rows where url is empty (safety for the repeater UI)
        if (isset($validated['video_links'])) {
            $validated['video_links'] = array_values(
                array_filter($validated['video_links'], fn($v) => !empty($v['url']))
            );
        }

        $politician->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    /**
     * Show the politician's referral dashboard.
     */
    public function referrals()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        // Voters recruited via this politician's referral link
        $referredVoters = $politician->referredVoters()
            ->latest()
            ->get();

        // Politicians recruited via this politician's referral link
        $referredPoliticians = $politician->referredPoliticians()
            ->latest()
            ->get();

        // Per-view commissions (voter_view type earned by this politician)
        $voterViewEarnings = $politician->referralEarnings()
            ->voterViews()
            ->with('referredVoter')
            ->latest()
            ->take(30)
            ->get();

        // Procurement commissions (politician_procurement type earned by this politician)
        $procurementEarnings = $politician->referralEarnings()
            ->procurements()
            ->with('politician')
            ->latest()
            ->get();

        $totalVoterViewEarnings  = (float) $politician->referralEarnings()->voterViews()->sum('commission_amount');
        $totalProcurementEarnings = (float) $politician->referralEarnings()->procurements()->sum('commission_amount');

        return view('standalone.politician.referrals', compact(
            'politician',
            'referredVoters',
            'referredPoliticians',
            'voterViewEarnings',
            'procurementEarnings',
            'totalVoterViewEarnings',
            'totalProcurementEarnings'
        ));
    }

    // ─────────────────────────────────────────────────────────────────────
    // Phase 13 — Public Profile Page Management
    // ─────────────────────────────────────────────────────────────────────

    /** Show the "Public Page" settings editor. */
    public function publicPage()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $page = $politician->page ?? new \App\Models\PoliticianPage(\App\Models\PoliticianPage::defaults($politician->id));
        $initiatives = $politician->initiatives()->get();

        return view('standalone.politician.public-page', compact('politician', 'page', 'initiatives'));
    }

    /** Save theme / layout / visibility settings for the public profile page. */
    public function updatePublicPage(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'layout_preset'       => 'required|in:classic,modern,bold,minimal',
            'primary_color'       => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accent_color'        => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_style'    => 'required|in:dark,light,gradient,image',
            'hero_banner_url'     => 'nullable|url|max:500',
            'hero_banner_file'    => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
            'hero_banner_edited'  => 'nullable|string',
            'show_bio'            => 'boolean',
            'show_initiatives'    => 'boolean',
            'show_campaigns'      => 'boolean',
            'show_contact'        => 'boolean',
            'custom_cta_text'     => 'nullable|string|max:80',
            'custom_cta_url'      => 'nullable|url|max:500',
            'page_published'      => 'boolean',
        ]);

        // Cast checkbox booleans (checkboxes are absent when unchecked)
        foreach (['show_bio', 'show_initiatives', 'show_campaigns', 'show_contact', 'page_published'] as $flag) {
            $validated[$flag] = $request->boolean($flag);
        }

        // ── Handle Hero Banner Upload ──
        $heroBannerUrl = $validated['hero_banner_url'] ?? null;

        // Priority 1: Edited image data (base64)
        if (!empty($validated['hero_banner_edited'])) {
            $heroBannerUrl = $this->storeBase64Image(
                $validated['hero_banner_edited'],
                'hero-banners',
                $politician->id
            );
        }
        // Priority 2: Uploaded file
        elseif ($request->hasFile('hero_banner_file')) {
            $file = $request->file('hero_banner_file');
            $filename = 'hero-banner-' . $politician->id . '-' . time() . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('public/hero-banners', $filename);
            $heroBannerUrl = asset('storage/' . str_replace('public/', '', $path));
        }
        // Priority 3: URL input (already in $validated)

        // Update hero_banner_url in validated data
        $validated['hero_banner_url'] = $heroBannerUrl;

        // Upsert politician_pages
        \App\Models\PoliticianPage::updateOrCreate(
            ['politician_id' => $politician->id],
            \Illuminate\Support\Arr::except($validated, ['page_published', 'hero_banner_file', 'hero_banner_edited'])
        );

        // Update page_published on the politician itself
        $politician->update(['page_published' => $validated['page_published']]);

        return back()->with('success', 'Public page settings saved.');
    }

    /**
     * Store base64 encoded image to disk
     */
    private function storeBase64Image(string $base64Data, string $directory, int $userId): ?string
    {
        try {
            // Extract base64 data and mime type
            if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $base64Data, $matches)) {
                $extension = $matches[1];
                $data = base64_decode($matches[2]);
                
                if ($data === false) {
                    return null;
                }

                // Generate filename
                $filename = $directory . '-' . $userId . '-' . time() . '.' . $extension;
                $path = "public/{$directory}/{$filename}";

                // Store file
                \Storage::put($path, $data);

                return asset('storage/' . str_replace('public/', '', $path));
            }

            return null;
        } catch (\Exception $e) {
            \Log::error('Failed to store base64 image: ' . $e->getMessage());
            return null;
        }
    }

    // ── Initiatives ────────────────────────────────────────────────────────

    /** Store a new platform initiative. */
    public function storeInitiative(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'title'        => 'required|string|max:120',
            'description'  => 'nullable|string|max:800',
            'icon'         => 'nullable|string|max:64',
            'sort_order'   => 'nullable|integer|min:0|max:9999',
            'is_published' => 'boolean',
        ]);
        $validated['is_published'] = $request->boolean('is_published', true);

        $politician->initiatives()->create($validated);

        return back()->with('success', 'Initiative added.');
    }

    /** Update an existing initiative. */
    public function updateInitiative(Request $request, \App\Models\PoliticianInitiative $initiative)
    {
        abort_unless($initiative->politician_id === Auth::user()->politician?->id, 403);

        $validated = $request->validate([
            'title'        => 'required|string|max:120',
            'description'  => 'nullable|string|max:800',
            'icon'         => 'nullable|string|max:64',
            'sort_order'   => 'nullable|integer|min:0|max:9999',
            'is_published' => 'boolean',
        ]);
        $validated['is_published'] = $request->boolean('is_published', true);

        $initiative->update($validated);

        return back()->with('success', 'Initiative updated.');
    }

    /** Delete an initiative. */
    public function destroyInitiative(\App\Models\PoliticianInitiative $initiative)
    {
        abort_unless($initiative->politician_id === Auth::user()->politician?->id, 403);

        $initiative->delete();

        return back()->with('success', 'Initiative removed.');
    }

    // ─── Phase 16: Profile Verification & Transparency Settings ───────────────

    /**
     * Show transparency settings page
     */
    public function transparencySettings()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $verificationService = app(\App\Services\ProfileVerificationService::class);
        $verificationStatus = $verificationService->getVerificationStatus($politician);

        return view('standalone.politician.transparency-settings', compact(
            'politician',
            'verificationStatus'
        ));
    }

    /**
     * Initiate profile verification
     */
    public function initiateVerification(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'government_email' => 'required|email',
        ]);

        $verificationService = app(\App\Services\ProfileVerificationService::class);

        try {
            $verificationService->initiateVerification(
                $politician,
                $validated['government_email']
            );

            return back()->with('success', 'Verification email sent! Please check ' . $validated['government_email']);
        } catch (\Exception $e) {
            return back()->withErrors(['government_email' => $e->getMessage()]);
        }
    }

    /**
     * Verify profile with token (from email link)
     */
    public function verifyProfile(string $token)
    {
        $verificationService = app(\App\Services\ProfileVerificationService::class);
        $politician = $verificationService->verifyToken($token);

        if (!$politician) {
            return redirect()->route('politician.transparency-settings')
                ->withErrors(['error' => 'Invalid or expired verification token']);
        }

        return redirect()->route('politician.transparency-settings')
            ->with('success', 'Profile verified successfully! You can now enable transparency data sources.');
    }

    /**
     * Update transparency data source toggles
     */
    public function updateTransparencySettings(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        // Only verified politicians can update transparency settings
        if ($politician->verification_status !== 'verified') {
            return back()->withErrors(['error' => 'You must verify your profile before enabling transparency features']);
        }

        $validated = $request->validate([
            'show_ballotpedia_data' => 'boolean',
            'show_opensecrets_data' => 'boolean',
            'show_votesmart_data' => 'boolean',
            'show_fec_data' => 'boolean',
        ]);

        // Convert null to false for checkboxes
        $validated['show_ballotpedia_data'] = $request->boolean('show_ballotpedia_data', false);
        $validated['show_opensecrets_data'] = $request->boolean('show_opensecrets_data', false);
        $validated['show_votesmart_data'] = $request->boolean('show_votesmart_data', false);
        $validated['show_fec_data'] = $request->boolean('show_fec_data', false);

        $politician->update($validated);

        // Clear cache for all enabled services
        if ($validated['show_ballotpedia_data']) {
            app(\App\Services\BallotpediaService::class)->clearCache($politician);
        }
        if ($validated['show_opensecrets_data']) {
            app(\App\Services\OpenSecretsService::class)->clearCache($politician);
        }
        if ($validated['show_votesmart_data']) {
            app(\App\Services\VoteSmartService::class)->clearCache($politician);
        }
        if ($validated['show_fec_data']) {
            app(\App\Services\FECService::class)->clearCache($politician);
        }

        return back()->with('success', 'Transparency settings updated');
    }
}

