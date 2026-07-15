<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Http\Controllers\Concerns\HandlesCampaignVideoUpload;
use App\Http\Controllers\Concerns\PaymentModeFilterable;
use App\Http\Controllers\Controller;
use App\Mail\CampaignReactivatedMail;
use App\Models\CandidateMatchReview;
use App\Models\DistrictLookupSearch;
use App\Models\EngagementSurveyResponse;
use App\Services\ReverbBroadcastService;
use App\Models\AdminSecurityAuditLog;
use App\Models\CampaignAuditLog;
use App\Models\CampaignTransaction;
use App\Models\OnboardingHandoffEvent;
use App\Models\PoliticalCampaign;
use App\Models\PoliticianCredit;
use App\Models\Politician;
use App\Models\CitizenCampaign;
use App\Models\PayoutAttempt;
use App\Models\ReferralEarning;
use App\Models\ReferralVisit;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\VoterWatchReport;
use App\Models\Voter;
use App\Notifications\CampaignStatusChangedNotification;
use App\Services\AdminTwoFactorService;
use App\Services\CampaignModerationService;
use App\Services\CampaignQuestionDigestService;
use App\Services\PoliticalPaymentService;
use App\Services\CampaignQandAService;
use App\Services\CampaignStatusNotifier;
use App\Services\PlatformSettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Standalone Admin Controller
 *
 * Handles admin-specific features in standalone mode:
 * - Campaign approval
 * - User management
 * - Fraud detection
 * - Payouts processing
 * - System settings (including SMTP email)
 */
class AdminController extends Controller
{
    use HandlesCampaignVideoUpload;
    use PaymentModeFilterable;

    // ── SMTP / Mailgun env keys this controller manages ─────────────────────
    private const SMTP_ENV_KEYS = [
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_ENCRYPTION',
        'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME',
    ];

    private const MAILGUN_ENV_KEYS = [
        'MAILGUN_DOMAIN',
        'MAILGUN_SECRET',
        'MAILGUN_ENDPOINT',
    ];

    private const ALL_ENV_KEYS = [
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_ENCRYPTION',
        'MAIL_FROM_ADDRESS',
        'MAIL_FROM_NAME',
        'MAILGUN_DOMAIN',
        'MAILGUN_SECRET',
        'MAILGUN_ENDPOINT',
    ];

    private function inferMediaTypeFromUrl(?string $url, ?string $fallback = null): ?string
    {
        $value = trim((string) ($url ?? ''));
        if ($value === '') {
            return $fallback;
        }

        if (preg_match('/(?:youtu\.be\/|youtube\.com\/(?:watch\?v=|embed\/))/i', $value) === 1) {
            return 'youtube';
        }

        if (preg_match('/vimeo\.com\/(?:video\/)?\d+/i', $value) === 1) {
            return 'vimeo';
        }

        if (preg_match('/\.m3u8(\?.*)?$/i', $value) === 1) {
            return 'hls_stream';
        }

        return $fallback ?? 'direct_file';
    }

    private function safeActiveTopics()
    {
        try {
            return \App\Models\PoliticianTopic::active()->orderBy('sort_order')->get();
        } catch (\Throwable $e) {
            Log::warning('Unable to load politician topics for admin campaign form', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Ensure campaigns entering voter inventory are backed by funding metadata.
     */
    private function ensureCampaignFundingForVoterInventory(PoliticalCampaign $campaign): void
    {
        $paymentStatus = (string) ($campaign->getRawOriginal('payment_status') ?? '');
        $hasStripeIntent = is_string($campaign->stripe_payment_intent_id)
            && trim($campaign->stripe_payment_intent_id) !== '';

        if (in_array($paymentStatus, [
            PaymentStatus::Captured->value,
            PaymentStatus::Authorized->value,
        ], true) && $hasStripeIntent) {
            return;
        }

        app(PoliticalPaymentService::class)->chargeCampaign($campaign);
    }

    /**
     * Campaign ids that have campaign-linked transaction activity in the active payment mode.
     *
     * Used by campaign accounting ledger/export to avoid labeling account-level funding
     * events as campaign payments.
     */
    private function modeScopedCampaignIdsWithLinkedTransactions(string $mode)
    {
        return $this->applyPaymentModeFilter(
            CampaignTransaction::query()
                ->select('campaign_id')
                ->whereNotNull('campaign_id')
                ->distinct(),
            $mode
        );
    }

    /**
     * Politician ids that have billing activity in the active payment mode.
     * Used to ensure campaign monitoring reflects the currently configured Stripe mode.
     */
    private function modeScopedPoliticianIds(string $mode)
    {
        $txPoliticianIds = $this->applyPaymentModeFilter(
            CampaignTransaction::query()->select('politician_id')->whereNotNull('politician_id')->distinct(),
            $mode
        );

        $creditPoliticianIds = $this->applyPaymentModeFilter(
            PoliticianCredit::query()->select('politician_id')->whereNotNull('politician_id')->distinct(),
            $mode
        );

        return $txPoliticianIds->union($creditPoliticianIds);
    }

    /**
     * Account-level funding events that are not linked to a specific campaign.
     */
    private function modeScopedAccountFundingQuery(string $mode)
    {
        return $this->applyPaymentModeFilter(
            CampaignTransaction::query()
                ->whereNull('campaign_id'),
            $mode
        )
            ->with('politician:id,full_name');
    }

    private function applyCampaignLedgerSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($term) {
            $scoped->whereHas('campaign', function (Builder $campaignQuery) use ($term) {
                $campaignQuery->where('title', 'like', '%' . $term . '%')
                    ->orWhereHas('politician', function (Builder $politicianQuery) use ($term) {
                        $politicianQuery->where('full_name', 'like', '%' . $term . '%')
                            ->orWhereHas('user', function (Builder $userQuery) use ($term) {
                                $userQuery->where('email', 'like', '%' . $term . '%');
                            });
                    });
            })->orWhereHas('voter', function (Builder $voterQuery) use ($term) {
                $voterQuery->where('full_name', 'like', '%' . $term . '%')
                    ->orWhere('email', 'like', '%' . $term . '%');
            })->orWhere('processor_reference', 'like', '%' . $term . '%');
        });
    }

    private function applyCampaignTransactionSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($term) {
            $scoped->whereHas('campaign', function (Builder $campaignQuery) use ($term) {
                $campaignQuery->where('title', 'like', '%' . $term . '%');
            })->orWhereHas('politician', function (Builder $politicianQuery) use ($term) {
                $politicianQuery->where('full_name', 'like', '%' . $term . '%')
                    ->orWhereHas('user', function (Builder $userQuery) use ($term) {
                        $userQuery->where('email', 'like', '%' . $term . '%');
                    });
            })->orWhere('stripe_payment_intent_id', 'like', '%' . $term . '%')
              ->orWhere('stripe_charge_id', 'like', '%' . $term . '%')
              ->orWhere('stripe_refund_id', 'like', '%' . $term . '%');
        });
    }

    private function applyAccountFundingSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);
        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $scoped) use ($term) {
            $scoped->whereHas('politician', function (Builder $politicianQuery) use ($term) {
                $politicianQuery->where('full_name', 'like', '%' . $term . '%')
                    ->orWhereHas('user', function (Builder $userQuery) use ($term) {
                        $userQuery->where('email', 'like', '%' . $term . '%');
                    });
            })->orWhere('stripe_payment_intent_id', 'like', '%' . $term . '%')
              ->orWhere('stripe_charge_id', 'like', '%' . $term . '%')
              ->orWhere('stripe_refund_id', 'like', '%' . $term . '%');
        });
    }

    /**
     * Show the admin dashboard.
     */
    public function dashboard()
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIds($activePaymentMode);
        $completedViewQuery = ViewSession::where('status', 'completed')
            ->whereIn('political_campaign_id', $campaignIds);
        $hasStripeAccountIdColumn = Cache::remember('schema.voters.stripe_account_id', 3600, fn () => Schema::hasColumn('voters', 'stripe_account_id'));
        $hasStripeAccountStatusColumn = Cache::remember('schema.voters.stripe_account_status', 3600, fn () => Schema::hasColumn('voters', 'stripe_account_status'));

        $legacyVoterBase = Voter::query()
            ->whereHas('user', function ($q) {
                $q->where('user_type', 'voter')
                    ->where(function ($legacy) {
                        $legacy->whereNotNull('kyc_document_path')
                            ->orWhereNotNull('idme_verified_at')
                            ->orWhereIn('kyc_status', ['pending', 'approved', 'rejected']);
                    });
            });

        $stats = [
            'total_users'       => User::count(),
            'total_politicians' => User::where('user_type', 'politician')->count(),
            'total_voters'      => User::where('user_type', 'voter')->count(),
            'pending_campaigns' => PoliticalCampaign::where('approval_status', 'pending')->count(),
            'total_campaigns'   => PoliticalCampaign::whereIn('id', $campaignIds)->count(),
            'active_campaigns'  => PoliticalCampaign::where('status', 'active')->whereIn('id', $campaignIds)->count(),
            'total_views'             => (clone $completedViewQuery)->count(),
            // Gross platform revenue = political platform_revenue + citizen amount_spent
            'political_revenue'       => (float) ((clone $completedViewQuery)->sum('platform_revenue') ?? 0),
            'citizen_revenue'         => (float) CitizenCampaign::sum('amount_spent'),
            'total_revenue'           => (float) ((clone $completedViewQuery)->sum('platform_revenue') ?? 0)
                                            + (float) CitizenCampaign::sum('amount_spent'),
            // EB-attributed: gross spread from sessions where the voter was EB-referred
            'eb_attributed_revenue'   => (float) ViewSession::where('status', 'completed')
                                            ->whereIn('political_campaign_id', $campaignIds)
                                            ->whereHas('voter', fn ($q) => $q->whereNotNull('earlybank_member_id'))
                                            ->sum('platform_revenue'),
            'total_payouts'           => (clone $completedViewQuery)->sum('voter_payout_amount') ?? 0,
            'kyc_pending'       => User::where('kyc_status', 'pending')
                                        ->where('user_type', 'politician')->count(),
            'authentic_user_verifier_legacy' => (clone $legacyVoterBase)->count(),
            'authentic_user_verifier_pending' => $hasStripeAccountIdColumn
                ? (
                    $hasStripeAccountStatusColumn
                        ? (clone $legacyVoterBase)
                            ->where(function ($q) {
                                $q->whereNull('stripe_account_id')
                                    ->orWhere('stripe_account_id', '')
                                    ->orWhereNull('stripe_account_status')
                                    ->orWhere('stripe_account_status', '!=', 'active');
                            })
                            ->count()
                        : (clone $legacyVoterBase)
                            ->where(function ($q) {
                                $q->whereNull('stripe_account_id')
                                    ->orWhere('stripe_account_id', '');
                            })
                            ->count()
                )
                : (clone $legacyVoterBase)->count(),
            'authentic_user_verifier_completed' => $hasStripeAccountIdColumn
                ? (
                    $hasStripeAccountStatusColumn
                        ? (clone $legacyVoterBase)
                            ->whereNotNull('stripe_account_id')
                            ->where('stripe_account_id', '!=', '')
                            ->where('stripe_account_status', 'active')
                            ->count()
                        : (clone $legacyVoterBase)
                            ->whereNotNull('stripe_account_id')
                            ->where('stripe_account_id', '!=', '')
                            ->count()
                )
                : 0,
            'pending_candidate_matches' => CandidateMatchReview::where('status', CandidateMatchReview::STATUS_PENDING)->count(),
            'suspended_users'   => User::whereNotNull('suspended_at')->count(),
            'flagged_fraud'     => Voter::where('flagged_for_fraud', true)->count(),
            // Early-bank enrollment
            'eb_enrolled'       => Voter::whereNotNull('earlybank_own_member_uuid')->count(),
            'eb_attributed'     => Voter::whereNotNull('earlybank_member_id')->count(),
            // Citizen campaigns
            'citizen_campaigns_active'  => CitizenCampaign::where('status', 'active')->count(),
            'citizen_campaigns_pending' => CitizenCampaign::where('approval_status', 'pending')->count(),
            // Unpaid wallet liability
            'unpaid_wallet_liability' => (float) Voter::sum('wallet_balance'),
        ];

        $recentUsers = User::latest()->take(5)->get();
        $recentCampaigns = PoliticalCampaign::with('politician')->latest()->take(5)->get();

        return view('standalone.admin.dashboard', compact('stats', 'recentUsers', 'recentCampaigns'));
    }

    /**
     * Show pending campaigns for approval.
     */
    public function pendingCampaigns()
    {
        $campaigns = PoliticalCampaign::with('politician.user')
            ->where('approval_status', 'pending')
            ->latest()
            ->paginate(20);

        $citizenCampaigns = \App\Models\CitizenCampaign::with('citizen.user')
            ->where('approval_status', 'pending')
            ->latest()
            ->paginate(20);

        return view('standalone.admin.campaigns-pending', compact('campaigns', 'citizenCampaigns'));
    }

    /**
     * Approve a citizen campaign (ballot-issue queue).
     *
     * Deliberately separate from approveCampaign (political) so the two
     * moderation queues remain independent for compliance auditing.
     * Mail/notification/broadcast wiring is deferred to Phase F.
     */
    public function approveCitizenCampaign(\App\Models\CitizenCampaign $campaign)
    {
        $newStatus = ($campaign->scheduled_start_at && $campaign->scheduled_start_at->isFuture())
            ? CampaignStatus::Scheduled->value
            : CampaignStatus::Active->value;

        $campaign->update([
            'approval_status' => ApprovalStatus::Approved->value,
            'status'          => $newStatus,
            'approved_at'     => now(),
            'started_at'      => $newStatus === CampaignStatus::Active->value ? now() : null,
        ]);

        // Note: CampaignAuditLog.campaign_id FK targets political_campaigns only.
        // Citizen campaign audit logging deferred to Phase F (polymorphic audit table).

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been approved.');
    }

    /**
     * Reject a citizen campaign (ballot-issue queue).
     */
    public function rejectCitizenCampaign(Request $request, \App\Models\CitizenCampaign $campaign)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $campaign->update([
            'approval_status'  => ApprovalStatus::Rejected->value,
            'status'           => CampaignStatus::Draft->value,
            'rejection_reason' => $request->input('reason', 'Does not meet content guidelines.'),
        ]);

        // Note: CampaignAuditLog.campaign_id FK targets political_campaigns only.
        // Citizen campaign audit logging deferred to Phase F (polymorphic audit table).

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been rejected.');
    }

    /**
     * Show all currently running (active + paused) campaigns across all politicians.
     * Includes spend data, voter interaction counts, and view progress.
     */
    public function runningCampaigns(Request $request)
    {
        $activePaymentMode = $this->activePaymentMode();
        $modePoliticianIds = $this->modeScopedPoliticianIds($activePaymentMode);

        $query = PoliticalCampaign::select('political_campaigns.*')
            ->selectRaw(
                '(SELECT COUNT(DISTINCT voter_id) FROM view_sessions
                  WHERE view_sessions.political_campaign_id = political_campaigns.id) as unique_voters_count'
            )
            ->selectRaw(
                '(SELECT COUNT(*) FROM view_sessions
                  WHERE view_sessions.political_campaign_id = political_campaigns.id
                    AND status = \'completed\') as completed_sessions_count'
            )
            ->selectRaw(
                '(SELECT ROUND(AVG(completion_percentage), 1) FROM view_sessions
                  WHERE view_sessions.political_campaign_id = political_campaigns.id
                    AND status = \'completed\') as avg_completion_pct'
            )
            ->with('politician.user')
            ->whereIn('status', [
                CampaignStatus::Active->value,
                CampaignStatus::Paused->value,
                CampaignStatus::Scheduled->value,
            ]);

        if ($activePaymentMode) {
            $query->whereIn('politician_id', $modePoliticianIds);
        }

        if ($search = $request->get('search')) {
            $query->whereHas('politician', fn ($q) =>
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('political_office', 'like', "%{$search}%")
            );
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->get('type')) {
            $query->where('campaign_type', $type);
        }

        $campaigns = $query
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END")
            ->latest('started_at')
            ->paginate(25)
            ->withQueryString();

        $summaryBase = PoliticalCampaign::whereIn('status', [
            CampaignStatus::Active->value,
            CampaignStatus::Paused->value,
            CampaignStatus::Scheduled->value,
        ]);

        if ($activePaymentMode) {
            $summaryBase->whereIn('politician_id', $modePoliticianIds);
        }

        $summary = [
            'total_active'    => (clone $summaryBase)->where('status', CampaignStatus::Active->value)->count(),
            'total_scheduled' => (clone $summaryBase)->where('status', CampaignStatus::Scheduled->value)->count(),
            'total_paused'    => (clone $summaryBase)->where('status', CampaignStatus::Paused->value)->count(),
            'total_spend'     => (clone $summaryBase)->sum('amount_spent'),
            'total_views'     => (clone $summaryBase)->sum('views_completed'),
        ];

        // ── Citizen campaigns (active / paused / scheduled) ──────────────
        $citizenRunningQuery = CitizenCampaign::with('citizen')
            ->whereIn('status', [
                CampaignStatus::Active->value,
                CampaignStatus::Paused->value,
                CampaignStatus::Scheduled->value,
            ]);

        if ($search = $request->get('search')) {
            $citizenRunningQuery->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhereHas('citizen', fn ($cq) => $cq->where('full_name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->get('status')) {
            $citizenRunningQuery->where('status', $status);
        }

        $citizenCampaigns = $citizenRunningQuery
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 WHEN status = 'scheduled' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(25, ['*'], 'citizen_page')
            ->withQueryString();

        $citizenSummary = [
            'total_active'    => CitizenCampaign::where('status', CampaignStatus::Active->value)->count(),
            'total_paused'    => CitizenCampaign::where('status', CampaignStatus::Paused->value)->count(),
            'total_scheduled' => CitizenCampaign::where('status', CampaignStatus::Scheduled->value)->count(),
            'total_spend'     => CitizenCampaign::sum('amount_spent'),
            'total_views'     => CitizenCampaign::sum('views_completed'),
        ];

        return view('standalone.admin.campaigns-running', compact('campaigns', 'summary', 'citizenCampaigns', 'citizenSummary'));
    }

    // ── Citizen campaign lifecycle actions ────────────────────────────────

    public function pauseCitizenCampaign(CitizenCampaign $campaign)
    {
        if ($campaign->status === CampaignStatus::Paused->value || $campaign->status?->value === CampaignStatus::Paused->value) {
            return back()->with('error', 'Campaign is already paused.');
        }

        $campaign->update(['status' => CampaignStatus::Paused->value]);

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been paused.');
    }

    public function stopCitizenCampaign(Request $request, CitizenCampaign $campaign)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $campaign->update([
            'status'           => CampaignStatus::Cancelled->value,
            'rejection_reason' => $request->input('reason') ?: 'Stopped by admin.',
            'completed_at'     => now(),
        ]);

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been stopped.');
    }

    public function reactivateCitizenCampaign(CitizenCampaign $campaign)
    {
        if ($campaign->approval_status !== ApprovalStatus::Approved->value
            && $campaign->approval_status?->value !== ApprovalStatus::Approved->value) {
            return back()->with('error', 'Only approved campaigns can be reactivated.');
        }

        $newStatus = ($campaign->scheduled_start_at && $campaign->scheduled_start_at->isFuture())
            ? CampaignStatus::Scheduled->value
            : CampaignStatus::Active->value;

        $campaign->update([
            'status'           => $newStatus,
            'rejection_reason' => null,
            'completed_at'     => null,
        ]);

        return back()->with('success', 'Citizen campaign "' . $campaign->title . '" has been reactivated.');
    }

    /**
     * Approve a campaign.
     * Delegates all status-transition, charging, audit-logging, and notification
     * logic to CampaignModerationService (single source of truth for PATT-004).
     */
    public function approveCampaign(PoliticalCampaign $campaign)
    {
        $result = app(CampaignModerationService::class)->approve($campaign, auth()->id());

        return back()->with('success', 'Campaign "' . $campaign->title . '" has been ' . $result['label'] . '.');
    }

    /**
     * Reject a campaign.
     * Delegates all status-transition, audit-logging, and notification logic
     * to CampaignModerationService (single source of truth for PATT-004).
     */
    public function rejectCampaign(Request $request, PoliticalCampaign $campaign)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $rejectionReason = $request->input('reason', 'Does not meet content guidelines.');

        app(CampaignModerationService::class)->reject($campaign, $rejectionReason, auth()->id());

        return back()->with('success', 'Campaign "' . $campaign->title . '" has been rejected.');
    }

    /**
     * Show the admin edit form for any campaign.
     */
    public function editCampaign(PoliticalCampaign $campaign)
    {
        $campaign->load('politician.user');

        // Use raw DB values for enum-backed columns so legacy/invalid values
        // do not crash the edit form rendering.
        $campaignStatusValue = (string) ($campaign->getRawOriginal('status') ?? '');
        $campaignApprovalStatusValue = (string) ($campaign->getRawOriginal('approval_status') ?? '');

        $auditLogs = CampaignAuditLog::where('campaign_id', $campaign->id)
            ->with('admin:id,name')
            ->latest()
            ->get();

        $states = config('u9itus.us_states', []);
        $topics = $this->safeActiveTopics();
        $campaignTopicIds = [];
        try {
            $campaignTopicIds = $campaign->topics()->pluck('id')->toArray();
        } catch (\Throwable $e) {
            Log::warning('Unable to load campaign topics for admin edit form', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }
        $governanceLevels = config('u9itus.governance_levels', [
            'Federal' => 'Federal', 'State' => 'State', 'County' => 'County',
            'City' => 'City', 'School Board' => 'School Board',
        ]);

        return view('standalone.admin.campaign-edit', compact('campaign', 'states', 'governanceLevels', 'auditLogs', 'topics', 'campaignTopicIds', 'campaignStatusValue', 'campaignApprovalStatusValue'));
    }

    /**
     * Update a campaign as admin (no status/ownership restrictions).
     * Diffs all changed fields and writes an immutable audit log entry.
     */
    public function updateCampaign(Request $request, PoliticalCampaign $campaign)
    {
        $videoMimeTypes = ['video/mp4', 'video/webm'];
        if (preg_match('/\b(iPhone|iPad|iPod)\b/i', $request->userAgent() ?? '') === 1) {
            $videoMimeTypes[] = 'video/quicktime';
        }

        $minVideoDuration = max(10, min(180, (int) config('u9itus.min_video_duration', 10)));
        $maxVideoDuration = max($minVideoDuration, min(180, (int) config('u9itus.max_video_duration', 180)));

        $validated = $request->validate([
            'title'                    => ['required', 'string', 'max:255'],
            'message_summary'          => ['nullable', 'string', 'max:2000'],
            'campaign_type'            => ['required', 'in:video,live_feed,q_and_a'],
            'governance_level'         => ['required', 'string', 'max:100'],
            'total_budget'             => ['required', 'numeric', 'min:0'],
            'total_views_requested'    => ['required', 'integer', 'min:0'],
            'target_states'            => ['nullable', 'array'],
            'target_states.*'          => ['string', 'max:2'],
            'target_cities'            => ['nullable', 'array'],
            'target_cities.*'          => ['string', 'max:100'],
            'media_type'               => ['nullable', 'in:youtube,vimeo,direct_file,s3_cloudfront,hls_stream'],
            'media_url'                => ['nullable', 'url'],
            'video'                    => ['nullable', 'file', 'mimetypes:' . implode(',', $videoMimeTypes), 'max:' . ((int) config('u9itus.max_video_size_mb', 1024) * 1024)],
            'media_duration'           => ['nullable', 'integer', 'min:' . $minVideoDuration, 'max:' . $maxVideoDuration],
            'live_feed_url'            => ['nullable', 'url'],
            'live_scheduled_at'        => ['nullable', 'date'],
            'scheduled_start_at'       => ['nullable', 'date'],
            'scheduled_end_at'         => ['nullable', 'date', 'after:scheduled_start_at'],
            'allow_repeat_views'       => ['nullable', 'boolean'],
            'repeat_view_cooldown_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
            'max_views_per_voter'      => ['nullable', 'integer', 'min:1', 'max:10'],
            'topic_ids'                => ['nullable', 'array', 'max:5'],
            'topic_ids.*'              => ['integer', 'exists:politician_topics,id'],
            'intro_text'               => ['nullable', 'string', 'max:1000'],
            'qa_items'                 => ['nullable', 'array'],
            'qa_items.*.question'      => ['nullable', 'string', 'max:500'],
            'qa_items.*.answer'        => ['nullable', 'string', 'max:2000'],
            'engagement_survey'          => ['nullable', 'array'],
            'engagement_survey.question' => ['nullable', 'string', 'max:200'],
            'engagement_survey.options'  => ['nullable', 'array'],
            'engagement_survey.options.*.text'  => ['nullable', 'string', 'max:100'],
            'engagement_survey.options.*.value' => ['nullable', 'string', 'max:10'],
            'min_watch_time_percent'   => ['nullable', 'integer', 'min:50', 'max:100'],
            'status'                   => ['required', 'in:draft,pending_approval,scheduled,active,paused,completed,cancelled'],
            'approval_status'          => ['required', 'in:pending,approved,rejected'],
            'rejection_reason'         => ['nullable', 'string', 'max:500'],
            'edit_reason'              => ['nullable', 'string', 'max:500'],
        ]);

        $uploadedVideo = $request->file('video');
        unset($validated['video']);

        if ($uploadedVideo) {
            // File uploads take precedence over URL input to avoid mixed-source state.
            unset($validated['media_url']);
            $validated['media_type'] = 'direct_file';
        } elseif (! empty($validated['media_url'])) {
            $validated['media_type'] = $this->inferMediaTypeFromUrl(
                $validated['media_url'],
                $validated['media_type'] ?? 'direct_file'
            );
        }

        $qaService = app(CampaignQandAService::class);
        if (isset($validated['topic_ids'])) {
            $qaService->syncTopics($campaign, $validated['topic_ids']);
            unset($validated['topic_ids']);
        }

        if (! empty($validated['qa_items'])) {
            $validated['qa_items'] = $qaService->parseQAItems($validated['qa_items']);
        }

        if (! empty($validated['engagement_survey'])) {
            $validated['engagement_survey'] = $qaService->parseEngagementSurvey($validated['engagement_survey']);
        }

        // Snapshot pre-update values for the diff (raw attributes, not cast)
        $trackFields = array_diff(array_keys($validated), ['edit_reason']);
        $before  = $campaign->only($trackFields);
        $reason  = $validated['edit_reason'] ?? null;
        unset($validated['edit_reason']);

        $campaign->update($validated);

        $statusValue = (string) ($campaign->getRawOriginal('status') ?? '');
        $approvalValue = (string) ($campaign->getRawOriginal('approval_status') ?? '');
        if ($statusValue === CampaignStatus::Active->value && $approvalValue === ApprovalStatus::Approved->value) {
            $this->ensureCampaignFundingForVoterInventory($campaign);
            $campaign->refresh();
        }

        if ($uploadedVideo) {
            $mediaUrl = $this->storeCampaignVideoAndGetUrl($uploadedVideo, $campaign);

            if (! $mediaUrl) {
                return redirect()
                    ->route('admin.campaigns.edit', $campaign)
                    ->withErrors(['video' => 'Campaign updated, but video upload failed. Please check storage settings and try again.']);
            }

            $campaign->update([
                'media_url' => $mediaUrl,
                'media_type' => 'direct_file',
            ]);
        }

        $diff = CampaignAuditLog::buildDiff($before, $validated);

        CampaignAuditLog::create([
            'campaign_id' => $campaign->id,
            'admin_id'    => auth()->id(),
            'action'      => 'edited',
            'reason'      => $reason,
            'changes'     => $diff ?: null,
        ]);

        return redirect()
            ->route('admin.campaigns.edit', $campaign)
            ->with('success', 'Campaign "' . $campaign->title . '" has been updated.');
    }

    /**
     * Force-pause (stop) an active campaign with a mandatory reason.
     */
    public function stopCampaign(Request $request, PoliticalCampaign $campaign)
    {
        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $campaign->update(['status' => CampaignStatus::Paused]);

        CampaignAuditLog::create([
            'campaign_id' => $campaign->id,
            'admin_id'    => auth()->id(),
            'action'      => 'stopped',
            'reason'      => $request->input('reason'),
        ]);

        app(CampaignQuestionDigestService::class)->queueDigestForCampaign($campaign);
        app(CampaignStatusNotifier::class)->notifyStatusChanged($campaign, 'stopped', $request->input('reason'));

        return back()->with('success', 'Campaign "' . $campaign->title . '" has been stopped.');
    }

    /**
     * Reactivate a previously stopped / paused campaign.
     */
    public function reactivateCampaign(Request $request, PoliticalCampaign $campaign)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $campaign->update(['status' => CampaignStatus::Active]);
        $this->ensureCampaignFundingForVoterInventory($campaign);
        $campaign->refresh();

        CampaignAuditLog::create([
            'campaign_id' => $campaign->id,
            'admin_id'    => auth()->id(),
            'action'      => 'reactivated',
            'reason'      => $request->input('reason'),
        ]);

        // Phase 11 — real-time WebSocket push to politician dashboard
        app(ReverbBroadcastService::class)->campaignReactivated($campaign);

        // Notify campaign owner (non-fatal)
        try {
            $politicianUser = $campaign->politician?->user;

            if ($politicianUser?->email) {
                Mail::to($politicianUser->email)
                    ->queue(new CampaignReactivatedMail($campaign));
            }

            if ($politicianUser) {
                $politicianUser->notify(
                    new CampaignStatusChangedNotification(
                        $campaign,
                        'reactivated',
                        $request->input('reason')
                    )
                );
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send campaign reactivation notifications', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Campaign "' . $campaign->title . '" has been reactivated.');
    }

    /**
     * Apply bulk actions from the Live Campaign Monitor.
     */
    public function bulkCampaignAction(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:stop,reactivate'],
            'campaign_ids' => ['required', 'array', 'min:1'],
            'campaign_ids.*' => ['integer', 'exists:political_campaigns,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $action = (string) $validated['action'];
        $reason = trim((string) ($validated['reason'] ?? ''));

        $campaignIds = collect($validated['campaign_ids'])
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        $campaigns = PoliticalCampaign::query()
            ->whereIn('id', $campaignIds)
            ->get();

        if ($campaigns->isEmpty()) {
            return back()->withErrors(['error' => 'No campaigns were selected.']);
        }

        $updated = 0;
        $defaultReason = $action === 'stop'
            ? 'Stopped by administrator (bulk action).'
            : 'Reactivated by administrator (bulk action).';
        $logReason = $reason !== '' ? $reason : $defaultReason;

        foreach ($campaigns as $campaign) {
            $statusValue = $campaign->status instanceof \BackedEnum
                ? $campaign->status->value
                : (string) $campaign->status;

            if ($action === 'stop') {
                if ($statusValue === CampaignStatus::Paused->value) {
                    continue;
                }

                $campaign->update(['status' => CampaignStatus::Paused]);

                CampaignAuditLog::create([
                    'campaign_id' => $campaign->id,
                    'admin_id' => auth()->id(),
                    'action' => 'stopped',
                    'reason' => $logReason,
                ]);

                app(ReverbBroadcastService::class)->campaignStopped($campaign, $logReason);
                $updated++;
                continue;
            }

            if ($statusValue === CampaignStatus::Active->value) {
                continue;
            }

            $campaign->update(['status' => CampaignStatus::Active]);
            $this->ensureCampaignFundingForVoterInventory($campaign);
            $campaign->refresh();

            CampaignAuditLog::create([
                'campaign_id' => $campaign->id,
                'admin_id' => auth()->id(),
                'action' => 'reactivated',
                'reason' => $logReason,
            ]);

            app(ReverbBroadcastService::class)->campaignReactivated($campaign);
            $updated++;
        }

        if ($updated === 0) {
            return back()->withErrors(['error' => 'No selected campaigns were eligible for that action.']);
        }

        $messageAction = $action === 'stop' ? 'stopped' : 'reactivated';

        return back()->with('success', $updated . ' campaign(s) ' . $messageAction . '.');
    }

    /**
     * Paginated audit log for a single campaign.
     */
    public function campaignAuditLog(PoliticalCampaign $campaign)
    {
        $campaign->load('politician.user');

        $auditLogs = CampaignAuditLog::where('campaign_id', $campaign->id)
            ->with('admin:id,name')
            ->latest()
            ->paginate(30);

        return view('standalone.admin.campaign-audit', compact('campaign', 'auditLogs'));
    }

    /**
     * Show analytics dashboard.
     */
    public function analytics()
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIds($activePaymentMode);
        $stats = $this->buildAnalyticsStats($campaignIds, $activePaymentMode);

        return view('standalone.admin.analytics', compact('stats', 'activePaymentMode'));
    }

    /**
     * Validate and sanitize a date query parameter.
     * Returns null if the value is not a valid YYYY-MM-DD string.
     */
    private function sanitizeDateParam(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private function buildAnalyticsStats($campaignIds, string $activePaymentMode): array
    {
        $completedViewQuery = ViewSession::where('status', ViewSessionStatus::Completed->value)
            ->whereIn('political_campaign_id', $campaignIds);

        $totals = (clone $completedViewQuery)
            ->selectRaw('COUNT(*) as total_views')
            ->selectRaw('COALESCE(SUM(platform_revenue), 0) as total_net_revenue')
            ->first();

        $totalViews = (int) ($totals->total_views ?? 0);
        $totalNetRevenue = (float) ($totals->total_net_revenue ?? 0);
        $totalPayouts = (float) (clone $completedViewQuery)
            ->where('payment_status', ViewPaymentStatus::Paid->value)
            ->sum('voter_payout_amount');
        $totalReferrals = (float) ReferralEarning::forPaymentMode($activePaymentMode)->sum('commission_amount');

        // Citizen campaign revenue (amount charged to citizens/orgs)
        $citizenRevenue = (float) CitizenCampaign::sum('amount_spent');

        // EB-attributed revenue: political platform spread from EB-referred voters only
        $ebAttributedRevenue = (float) ViewSession::where('status', ViewSessionStatus::Completed->value)
            ->whereIn('political_campaign_id', $campaignIds)
            ->whereHas('voter', fn ($q) => $q->whereNotNull('earlybank_member_id'))
            ->sum('platform_revenue');

        // Gross platform revenue = political spread + citizen revenue
        // (voter payouts and referrals are costs, not separate revenue lines here)
        $grossDeliveredRevenue = $totalNetRevenue + $totalPayouts + $totalReferrals + $citizenRevenue;

        // Defensive: check if onboarding_handoff_events table exists (may not be migrated in production)
        $handoffRows = collect();
        if (\Schema::hasTable('onboarding_handoff_events')) {
            try {
                $handoffRows = OnboardingHandoffEvent::query()
                    ->whereIn('role', ['voter', 'politician'])
                    ->where('created_at', '>=', now()->subDays(30))
                    ->selectRaw("role")
                    ->selectRaw("SUM(CASE WHEN event_type = 'opened' THEN 1 ELSE 0 END) as opened_count")
                    ->selectRaw("SUM(CASE WHEN event_type = 'dismissed' THEN 1 ELSE 0 END) as dismissed_count")
                    ->selectRaw("COUNT(DISTINCT CASE WHEN event_type = 'opened' THEN user_id END) as unique_openers")
                    ->selectRaw("COUNT(DISTINCT CASE WHEN event_type = 'dismissed' THEN user_id END) as unique_dismissers")
                    ->groupBy('role')
                    ->get()
                    ->keyBy('role');
            } catch (\Throwable $e) {
                \Log::warning('Failed to query onboarding_handoff_events for analytics', ['error' => $e->getMessage()]);
                $handoffRows = collect();
            }
        }

        $buildHandoffRoleStats = function (string $role) use ($handoffRows): array {
            $row = $handoffRows->get($role);
            $opened = (int) ($row->opened_count ?? 0);
            $dismissed = (int) ($row->dismissed_count ?? 0);
            $uniqueOpeners = (int) ($row->unique_openers ?? 0);
            $uniqueDismissers = (int) ($row->unique_dismissers ?? 0);

            return [
                'opened' => $opened,
                'dismissed' => $dismissed,
                'unique_openers' => $uniqueOpeners,
                'unique_dismissers' => $uniqueDismissers,
                'dismiss_rate_pct' => $opened > 0 ? round(($dismissed / $opened) * 100, 1) : 0.0,
            ];
        };

        $voterHandoffStats = $buildHandoffRoleStats('voter');
        $politicianHandoffStats = $buildHandoffRoleStats('politician');

        // ── Early-bank enrollment ──────────────────────────────────────────
        $totalVoters    = Voter::count();
        $ebEnrolled     = Voter::whereNotNull('earlybank_own_member_uuid')->count();
        $ebAttributed   = Voter::whereNotNull('earlybank_member_id')->count();
        $ebEnrollRate   = $totalVoters > 0 ? round(($ebEnrolled / $totalVoters) * 100, 1) : 0.0;

        // ── Citizen campaigns ──────────────────────────────────────────────
        $citizenTotals = CitizenCampaign::query()
            ->selectRaw('COUNT(*) as total_campaigns')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_campaigns")
            ->selectRaw("SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_campaigns")
            ->selectRaw('COALESCE(SUM(amount_spent), 0) as total_revenue')
            ->selectRaw('COALESCE(SUM(views_completed), 0) as total_views')
            ->first();

        // ── Referral funnel (all-time platform-wide) ───────────────────────
        $refVisitBase         = ReferralVisit::query();
        $refTotalVisits       = (clone $refVisitBase)->count();
        $refUniqueVisitors    = (clone $refVisitBase)->whereNotNull('session_id')->distinct('session_id')->count('session_id');
        $refConversions       = (clone $refVisitBase)->whereNotNull('converted_at')->count();
        $refConversionRate    = $refTotalVisits > 0 ? round(($refConversions / $refTotalVisits) * 100, 1) : 0.0;

        // ── Payout health ─────────────────────────────────────────────────
        $unpaidLiability = (float) ViewSession::where('status', ViewSessionStatus::Completed->value)
            ->whereIn('payment_status', [ViewPaymentStatus::Pending->value, ViewPaymentStatus::Approved->value])
            ->sum('voter_payout_amount');

        $payoutAttemptCounts = PayoutAttempt::query()
            ->selectRaw("status, COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount")
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $payoutByMethod = PayoutAttempt::query()
            ->selectRaw("processor, COUNT(*) as total, COALESCE(SUM(amount), 0) as total_amount")
            ->groupBy('processor')
            ->get()
            ->keyBy('processor');

        $totalPayoutAttempts = $payoutAttemptCounts->sum('total');
        $failedAttempts      = (int) ($payoutAttemptCounts->get('failed')?->total ?? 0);
        $payoutFailRate      = $totalPayoutAttempts > 0 ? round(($failedAttempts / $totalPayoutAttempts) * 100, 1) : 0.0;

        // ── Voter payment method breakdown ────────────────────────────────
        $paymentMethodBreakdown = Voter::query()
            ->selectRaw("COALESCE(payment_method, 'not_set') as method, COUNT(*) as total")
            ->groupBy('method')
            ->pluck('total', 'method');

        // ── User growth (last 12 weeks, bucketed by ISO year-week) ──────────
        $driver = \DB::connection()->getDriverName();
        $weekExpr = $driver === 'sqlite'
            ? "strftime('%Y-%W', created_at)"
            : "DATE_FORMAT(created_at, '%Y-%u')";

        $userGrowth = User::query()
            ->selectRaw("{$weekExpr} as week_start")
            ->selectRaw('COUNT(*) as new_users')
            ->where('created_at', '>=', now()->subWeeks(12))
            ->groupBy('week_start')
            ->orderBy('week_start')
            ->get()
            ->map(fn ($r) => ['week' => $r->week_start, 'count' => (int) $r->new_users]);

        // ── Fraud session rate (completed sessions with score > 50) ───────
        $totalCompletedSessions = (clone $completedViewQuery)->count();
        $fraudSessions = ViewSession::where('status', ViewSessionStatus::Completed->value)
            ->whereIn('political_campaign_id', $campaignIds)
            ->where('fraud_score', '>', 50)
            ->count();
        $fraudSessionRate = $totalCompletedSessions > 0
            ? round(($fraudSessions / $totalCompletedSessions) * 100, 1)
            : 0.0;

        $totalPoliticalViews = $totalViews;
        $totalAllViews = $totalPoliticalViews + (int) ($citizenTotals->total_views ?? 0);

        return [
            'total_views' => $totalViews,
            'gross_revenue' => $grossDeliveredRevenue,
            'political_revenue' => $totalNetRevenue,
            'citizen_revenue' => $citizenRevenue,
            'eb_attributed_revenue' => $ebAttributedRevenue,
            'net_revenue' => $totalNetRevenue,
            'total_payouts' => $totalPayouts,
            'total_referrals' => $totalReferrals,
            'total_campaigns' => PoliticalCampaign::whereIn('id', $campaignIds)->count(),
            'active_campaigns' => PoliticalCampaign::where('status', CampaignStatus::Active->value)->whereIn('id', $campaignIds)->count(),
            'avg_revenue_per_view' => $totalViews > 0 ? round($grossDeliveredRevenue / $totalViews, 2) : 0.0,
            'avg_payout_per_view' => $totalViews > 0 ? round($totalPayouts / $totalViews, 2) : 0.0,
            'avg_referral_per_view' => $totalViews > 0 ? round($totalReferrals / $totalViews, 2) : 0.0,
            'avg_profit_per_view' => $totalViews > 0 ? round($totalNetRevenue / $totalViews, 2) : 0.0,
            'margin_percent' => $grossDeliveredRevenue > 0 ? round(($totalNetRevenue / $grossDeliveredRevenue) * 100, 1) : 0.0,
            'onboarding_handoff' => [
                'window_days' => 30,
                'voter' => $voterHandoffStats,
                'politician' => $politicianHandoffStats,
                'total_opened' => $voterHandoffStats['opened'] + $politicianHandoffStats['opened'],
                'total_dismissed' => $voterHandoffStats['dismissed'] + $politicianHandoffStats['dismissed'],
            ],
            // Early-bank
            'earlybank' => [
                'enrolled'       => $ebEnrolled,
                'attributed'     => $ebAttributed,
                'total_voters'   => $totalVoters,
                'enroll_rate_pct' => $ebEnrollRate,
            ],
            // Citizen campaigns
            'citizen_campaigns' => [
                'total'    => (int) ($citizenTotals->total_campaigns ?? 0),
                'active'   => (int) ($citizenTotals->active_campaigns ?? 0),
                'pending'  => (int) ($citizenTotals->pending_campaigns ?? 0),
                'revenue'  => (float) ($citizenTotals->total_revenue ?? 0),
                'views'    => (int) ($citizenTotals->total_views ?? 0),
            ],
            // Referral funnel
            'referral_funnel' => [
                'total_visits'      => $refTotalVisits,
                'unique_visitors'   => $refUniqueVisitors,
                'conversions'       => $refConversions,
                'conversion_rate_pct' => $refConversionRate,
            ],
            // Payout health
            'payout_health' => [
                'unpaid_liability'  => $unpaidLiability,
                'total_attempts'    => (int) $totalPayoutAttempts,
                'failed_attempts'   => $failedAttempts,
                'fail_rate_pct'     => $payoutFailRate,
                'by_status'         => $payoutAttemptCounts->map(fn ($r) => ['total' => (int) $r->total, 'amount' => (float) $r->total_amount]),
                'by_method'         => $payoutByMethod->map(fn ($r) => ['total' => (int) $r->total, 'amount' => (float) $r->total_amount]),
            ],
            // Voter payment method breakdown
            'payment_method_breakdown' => $paymentMethodBreakdown,
            // User growth (12-week time series)
            'user_growth' => $userGrowth,
            // Fraud
            'fraud_session_count' => $fraudSessions,
            'fraud_session_rate_pct' => $fraudSessionRate,
        ];
    }

    /**
     * Review district lookup traffic and discovery effectiveness.
     */
    public function districtSearches(Request $request)
    {
        $query = $this->buildDistrictSearchesQuery($request);

        $searches = $query
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        $stats = [
            'total' => DistrictLookupSearch::count(),
            'resolved' => DistrictLookupSearch::where('resolved', true)->count(),
            'unresolved' => DistrictLookupSearch::where('resolved', false)->count(),
            'officials_discovered' => (int) DistrictLookupSearch::sum('discovered_officials_count'),
        ];

        $sourceCounts = DistrictLookupSearch::query()
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->orderByDesc('total')
            ->pluck('total', 'source');

        return view('standalone.admin.district-searches', compact('searches', 'stats', 'sourceCounts'));
    }

    /**
     * Export district search results as CSV.
     */
    public function exportDistrictSearches(Request $request)
    {
        $searches = $this->buildDistrictSearchesQuery($request)
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get([
                'created_at',
                'query_address',
                'matched_address',
                'state',
                'district_code',
                'resolved',
                'source',
                'discovered_officials_count',
                'ip_address',
                'error_message',
            ]);

        $filename = 'district-searches-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($searches) {
            $output = fopen('php://output', 'w');

            fputcsv($output, [
                'When',
                'Query Address',
                'Matched Address',
                'State',
                'District Code',
                'Resolved',
                'Source',
                'Source Label',
                'Officials Discovered',
                'IP Address',
                'Error Message',
            ]);

            foreach ($searches as $search) {
                fputcsv($output, [
                    optional($search->created_at)->toDateTimeString(),
                    $search->query_address,
                    $search->matched_address,
                    $search->state,
                    $search->district_code,
                    $search->resolved ? 'Yes' : 'No',
                    $search->source,
                    $this->formatDistrictSearchSourceLabel($search->source),
                    $search->discovered_officials_count,
                    $search->ip_address,
                    $search->error_message,
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Build district searches query with filters shared by list and export.
     */
    private function buildDistrictSearchesQuery(Request $request): Builder
    {
        $query = DistrictLookupSearch::query();

        if ($q = trim((string) $request->query('q', ''))) {
            $query->where(function ($inner) use ($q) {
                $inner->where('query_address', 'like', "%{$q}%")
                    ->orWhere('matched_address', 'like', "%{$q}%")
                    ->orWhere('district_code', 'like', "%{$q}%");
            });
        }

        if (($resolved = $request->query('resolved')) !== null && $resolved !== '') {
            $query->where('resolved', filter_var($resolved, FILTER_VALIDATE_BOOLEAN));
        }

        if ($source = trim((string) $request->query('source', ''))) {
            $query->where('source', $source);
        }

        if ($state = strtoupper(trim((string) $request->query('state', '')))) {
            $query->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state]);
        }

        return $query;
    }

    /**
     * Convert district search source key to a readable label.
     */
    private function formatDistrictSearchSourceLabel(?string $source): string
    {
        return match ($source) {
            'census_geocoder' => 'Census Geocoder',
            'google_civic' => 'Google Civic',
            null, '' => 'Unknown',
            default => ucwords(str_replace('_', ' ', $source)),
        };
    }

    /**
     * Campaign accounting ledger UI — paginated transaction + session rows with filters.
     */
    public function campaignAccountingLedger(Request $request)
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIdsWithLinkedTransactions($activePaymentMode);

        $from           = $this->sanitizeDateParam($request->get('from'));
        $to             = $this->sanitizeDateParam($request->get('to'));
        $campaignFilter = $request->integer('campaign_id') ?: null;
        $rawSearch      = $request->get('campaign_search');
        $campaignSearch = is_string($rawSearch) && $rawSearch !== '' ? mb_substr(trim($rawSearch), 0, 100) : null;
        $tab            = in_array($request->get('tab'), ['transactions', 'sessions']) ? $request->get('tab') : 'sessions';

        $sessionsQuery = $this->applyCampaignLedgerSearch(
            ViewSession::query()
                ->with([
                    'campaign:id,title,politician_id',
                    'campaign.politician:id,full_name',
                    'voter:id,full_name,email',
                ])
                ->whereIn('political_campaign_id', $campaignIds)
                ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
                ->when($campaignFilter, fn($q) => $q->where('political_campaign_id', $campaignFilter))
                ->orderBy('created_at', 'desc'),
            (string) ($campaignSearch ?? '')
        );

        $transactionsQuery = $this->applyCampaignTransactionSearch(
            $this->applyPaymentModeFilter(
                CampaignTransaction::query()->whereIn('campaign_id', $campaignIds),
                $activePaymentMode
            )
                ->with(['politician:id,full_name', 'campaign:id,title'])
                ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
                ->when($campaignFilter, fn($q) => $q->where('campaign_id', $campaignFilter))
                ->orderBy('created_at', 'desc'),
            (string) ($campaignSearch ?? '')
        );

        $accountFundingQuery = $this->applyAccountFundingSearch(
            $this->modeScopedAccountFundingQuery($activePaymentMode)
                ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
                ->orderBy('created_at', 'desc'),
            (string) ($campaignSearch ?? '')
        );

        $sessions     = $sessionsQuery->paginate(50, ['*'], 's_page')->withQueryString();
        $transactions = $transactionsQuery->paginate(50, ['*'], 't_page')->withQueryString();
        $accountFunding = $accountFundingQuery->paginate(20, ['*'], 'af_page')->withQueryString();

        $totalsQuery = ViewSession::whereIn('political_campaign_id', $campaignIds)
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($campaignFilter, fn($q) => $q->where('political_campaign_id', $campaignFilter))
            ->selectRaw('COUNT(*) as total_sessions, COALESCE(SUM(platform_revenue),0) as total_revenue, COALESCE(SUM(voter_payout_amount),0) as total_payouts, COALESCE(SUM(referral_commission),0) as total_referrals');

        if ($campaignSearch) {
            $totalsQuery = $this->applyCampaignLedgerSearch($totalsQuery, $campaignSearch);
        }

        $totals = $totalsQuery
            ->first();

        $campaigns = PoliticalCampaign::whereIn('id', $campaignIds)->orderBy('title')->get(['id', 'title']);

        return view('standalone.admin.accounting-campaign', compact(
            'activePaymentMode', 'sessions', 'transactions', 'totals', 'campaigns', 'accountFunding',
            'from', 'to', 'campaignFilter', 'campaignSearch', 'tab'
        ));
    }

    /**
     * Voter accounting ledger UI — paginated session + referral rows with filters.
     */
    public function voterAccountingLedger(Request $request)
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIds($activePaymentMode);

        $from        = $this->sanitizeDateParam($request->get('from'));
        $to          = $this->sanitizeDateParam($request->get('to'));
        $rawSearch   = $request->get('voter_search');
        $voterSearch = is_string($rawSearch) && $rawSearch !== '' ? mb_substr(trim($rawSearch), 0, 100) : null;
        $tab         = in_array($request->get('tab'), ['sessions', 'referrals']) ? $request->get('tab') : 'sessions';

        $sessionsQuery = ViewSession::query()
            ->with([
                'campaign:id,title',
                'voter:id,full_name,email,payment_method,paypal_email,cashapp_tag',
            ])
            ->whereIn('political_campaign_id', $campaignIds)
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($voterSearch, fn($q) => $q->whereHas('voter', fn($vq) =>
                $vq->where('full_name', 'like', '%' . $voterSearch . '%')
                   ->orWhere('email', 'like', '%' . $voterSearch . '%')
            ))
            ->orderBy('created_at', 'desc');

        $referralsQuery = ReferralEarning::query()
            ->with([
                'referrer:id,full_name,email,payment_method,paypal_email,cashapp_tag',
                'viewSession:id,uuid,political_campaign_id,voter_id,status,payment_status,paid_at,created_at',
                'viewSession.campaign:id,title',
            ])
            ->whereHas('viewSession', fn($q) => $q->whereIn('political_campaign_id', $campaignIds))
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($voterSearch, fn($q) => $q->whereHas('referrer', fn($vq) =>
                $vq->where('full_name', 'like', '%' . $voterSearch . '%')
                   ->orWhere('email', 'like', '%' . $voterSearch . '%')
            ))
            ->orderBy('created_at', 'desc');

        $sessions  = $sessionsQuery->paginate(50, ['*'], 's_page')->withQueryString();
        $referrals = $referralsQuery->paginate(50, ['*'], 'r_page')->withQueryString();

        $totals = ViewSession::whereIn('political_campaign_id', $campaignIds)
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($voterSearch, fn($q) => $q->whereHas('voter', fn($vq) =>
                $vq->where('full_name', 'like', '%' . $voterSearch . '%')
                   ->orWhere('email', 'like', '%' . $voterSearch . '%')
            ))
            ->selectRaw('COUNT(*) as total_sessions, COALESCE(SUM(voter_payout_amount),0) as total_payouts, COALESCE(SUM(referral_commission),0) as total_referrals')
            ->first();

        return view('standalone.admin.accounting-voter', compact(
            'activePaymentMode', 'sessions', 'referrals', 'totals',
            'from', 'to', 'voterSearch', 'tab'
        ));
    }

    /**
     * Export campaign-level accounting rows and monthly rollups.
     */
    public function exportCampaignAccounting(Request $request)
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIdsWithLinkedTransactions($activePaymentMode);

        $from           = $this->sanitizeDateParam($request->get('from'));
        $to             = $this->sanitizeDateParam($request->get('to'));
        $campaignFilter = $request->integer('campaign_id') ?: null;
        $rawSearch      = $request->get('campaign_search');
        $campaignSearch = is_string($rawSearch) && $rawSearch !== '' ? mb_substr(trim($rawSearch), 0, 100) : null;

        $transactions = $this->applyCampaignTransactionSearch(
            $this->applyPaymentModeFilter(
                CampaignTransaction::query()->whereIn('campaign_id', $campaignIds),
                $activePaymentMode
            )
                ->with('politician:id,full_name')
                ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
                ->when($campaignFilter, fn($q) => $q->where('campaign_id', $campaignFilter))
                ->orderBy('created_at')
                ->limit(20000),
            (string) ($campaignSearch ?? '')
        )->get();

        $sessions = $this->applyCampaignLedgerSearch(
            ViewSession::query()
                ->with([
                    'campaign:id,title,politician_id',
                    'campaign.politician:id,full_name',
                    'voter:id,full_name,email',
                ])
                ->whereIn('political_campaign_id', $campaignIds)
                ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
                ->when($campaignFilter, fn($q) => $q->where('political_campaign_id', $campaignFilter))
                ->orderBy('created_at')
                ->limit(20000),
            (string) ($campaignSearch ?? '')
        )->get();

        $accountFunding = $this->applyAccountFundingSearch(
            $this->modeScopedAccountFundingQuery($activePaymentMode)
                ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
                ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
                ->orderBy('created_at')
                ->limit(20000),
            (string) ($campaignSearch ?? '')
        )->get();

        $campaignTitleMap = PoliticalCampaign::query()
            ->whereIn('id', $campaignIds)
            ->pluck('title', 'id');

        $isTruncated = $transactions->count() >= 20000 || $sessions->count() >= 20000 || $accountFunding->count() >= 20000;
        $filename = 'campaign-accounting-' . $activePaymentMode . '-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($transactions, $sessions, $accountFunding, $campaignTitleMap, $activePaymentMode, $isTruncated) {
            $output = fopen('php://output', 'w');

            if ($isTruncated) {
                fputcsv($output, ['WARNING: Export truncated at 20,000 rows per data type. Apply date or campaign filters to narrow results.']);
                fputcsv($output, []);
            }

            fputcsv($output, [
                'Generated At',
                now()->toDateTimeString(),
                'Payment Mode',
                $activePaymentMode,
            ]);
            fputcsv($output, []);

            fputcsv($output, [
                'Record Type',
                'Record ID',
                'Record UUID',
                'Created At',
                'Accounting Month',
                'Campaign ID',
                'Campaign Title',
                'Politician ID',
                'Politician Name',
                'Voter ID',
                'Voter Name',
                'Status',
                'Payment Status',
                'Transaction Type',
                'Payment Intent ID',
                'Charge ID',
                'Refund ID',
                'Currency',
                'Transaction Amount',
                'Platform Revenue',
                'Voter Payout',
                'Referral Commission',
                'Net Platform Amount',
            ]);

            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    'campaign_transaction',
                    $transaction->id,
                    $transaction->uuid,
                    optional($transaction->created_at)->toDateTimeString(),
                    optional($transaction->created_at)->format('Y-m'),
                    $transaction->campaign_id,
                    $campaignTitleMap->get($transaction->campaign_id),
                    $transaction->politician_id,
                    optional($transaction->politician)->full_name,
                    '',
                    '',
                    $transaction->status,
                    '',
                    $transaction->transaction_type,
                    $transaction->stripe_payment_intent_id,
                    $transaction->stripe_charge_id,
                    $transaction->stripe_refund_id,
                    strtoupper((string) $transaction->currency),
                    number_format((float) ($transaction->amount ?? 0), 2, '.', ''),
                    '',
                    '',
                    '',
                    '',
                ]);
            }

            foreach ($sessions as $session) {
                $campaign = $session->campaign;
                $platformRevenue = (float) ($session->platform_revenue ?? 0);
                $voterPayout = (float) ($session->voter_payout_amount ?? 0);
                $referralCommission = (float) ($session->referral_commission ?? 0);
                $status = (string) ($session->getRawOriginal('status') ?? '');
                $paymentStatus = (string) ($session->getRawOriginal('payment_status') ?? '');
                $monthDate = $session->completed_at ?? $session->created_at;

                fputcsv($output, [
                    'view_session',
                    $session->id,
                    $session->uuid,
                    optional($session->created_at)->toDateTimeString(),
                    optional($monthDate)->format('Y-m'),
                    $session->political_campaign_id,
                    optional($campaign)->title,
                    optional($campaign)->politician_id,
                    optional(optional($campaign)->politician)->full_name,
                    $session->voter_id,
                    optional($session->voter)->full_name,
                    $status,
                    $paymentStatus,
                    '',
                    '',
                    '',
                    '',
                    'USD',
                    '',
                    number_format($platformRevenue, 2, '.', ''),
                    number_format($voterPayout, 2, '.', ''),
                    number_format($referralCommission, 2, '.', ''),
                    number_format($platformRevenue - $voterPayout - $referralCommission, 2, '.', ''),
                ]);
            }

            $monthly = [];
            foreach ($transactions as $transaction) {
                $month = optional($transaction->created_at)->format('Y-m') ?? 'unknown';
                $campaignKey = (string) ($transaction->campaign_id ?? 0);
                $key = $month . '|' . $campaignKey;

                if (!isset($monthly[$key])) {
                    $monthly[$key] = [
                        'month' => $month,
                        'campaign_id' => $transaction->campaign_id,
                        'campaign_title' => $campaignTitleMap->get($transaction->campaign_id),
                        'charge_total' => 0.0,
                        'refund_total' => 0.0,
                        'platform_revenue_total' => 0.0,
                        'voter_payout_total' => 0.0,
                        'referral_total' => 0.0,
                        'session_net_total' => 0.0,
                        'completed_sessions' => 0,
                    ];
                }

                $amount = (float) ($transaction->amount ?? 0);
                if ((string) $transaction->transaction_type === 'refund') {
                    $monthly[$key]['refund_total'] += abs($amount);
                } else {
                    $monthly[$key]['charge_total'] += $amount;
                }
            }

            foreach ($sessions as $session) {
                $monthDate = $session->completed_at ?? $session->created_at;
                $month = optional($monthDate)->format('Y-m') ?? 'unknown';
                $campaignKey = (string) ($session->political_campaign_id ?? 0);
                $key = $month . '|' . $campaignKey;

                if (!isset($monthly[$key])) {
                    $monthly[$key] = [
                        'month' => $month,
                        'campaign_id' => $session->political_campaign_id,
                        'campaign_title' => $campaignTitleMap->get($session->political_campaign_id),
                        'charge_total' => 0.0,
                        'refund_total' => 0.0,
                        'platform_revenue_total' => 0.0,
                        'voter_payout_total' => 0.0,
                        'referral_total' => 0.0,
                        'session_net_total' => 0.0,
                        'completed_sessions' => 0,
                    ];
                }

                $platformRevenue = (float) ($session->platform_revenue ?? 0);
                $voterPayout = (float) ($session->voter_payout_amount ?? 0);
                $referralCommission = (float) ($session->referral_commission ?? 0);

                $monthly[$key]['platform_revenue_total'] += $platformRevenue;
                $monthly[$key]['voter_payout_total'] += $voterPayout;
                $monthly[$key]['referral_total'] += $referralCommission;
                $monthly[$key]['session_net_total'] += $platformRevenue - $voterPayout - $referralCommission;
                if ((string) ($session->getRawOriginal('status') ?? '') === 'completed') {
                    $monthly[$key]['completed_sessions']++;
                }
            }

            ksort($monthly);
            fputcsv($output, []);
            fputcsv($output, ['Monthly Rollup']);
            fputcsv($output, [
                'Month',
                'Campaign ID',
                'Campaign Title',
                'Charge Total',
                'Refund Total',
                'Session Platform Revenue Total',
                'Session Voter Payout Total',
                'Session Referral Total',
                'Session Net Total',
                'Completed Sessions',
            ]);

            foreach ($monthly as $row) {
                fputcsv($output, [
                    $row['month'],
                    $row['campaign_id'],
                    $row['campaign_title'],
                    number_format((float) $row['charge_total'], 2, '.', ''),
                    number_format((float) $row['refund_total'], 2, '.', ''),
                    number_format((float) $row['platform_revenue_total'], 2, '.', ''),
                    number_format((float) $row['voter_payout_total'], 2, '.', ''),
                    number_format((float) $row['referral_total'], 2, '.', ''),
                    number_format((float) $row['session_net_total'], 2, '.', ''),
                    $row['completed_sessions'],
                ]);
            }

            fputcsv($output, []);
            fputcsv($output, ['Account-Level Funding Events (Not Campaign-Linked)']);
            fputcsv($output, [
                'Record Type',
                'Record ID',
                'Created At',
                'Politician ID',
                'Politician Name',
                'Status',
                'Transaction Type',
                'Payment Intent ID',
                'Charge ID',
                'Refund ID',
                'Currency',
                'Amount',
            ]);

            foreach ($accountFunding as $funding) {
                fputcsv($output, [
                    'account_funding',
                    $funding->id,
                    optional($funding->created_at)->toDateTimeString(),
                    $funding->politician_id,
                    optional($funding->politician)->full_name,
                    $funding->status,
                    $funding->transaction_type,
                    $funding->stripe_payment_intent_id,
                    $funding->stripe_charge_id,
                    $funding->stripe_refund_id,
                    strtoupper((string) $funding->currency),
                    number_format((float) ($funding->amount ?? 0), 2, '.', ''),
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Export voter-level accounting rows and monthly rollups.
     */
    public function exportVoterAccounting(Request $request)
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIds($activePaymentMode);

        $from        = $this->sanitizeDateParam($request->get('from'));
        $to          = $this->sanitizeDateParam($request->get('to'));
        $rawSearch   = $request->get('voter_search');
        $voterSearch = is_string($rawSearch) && $rawSearch !== '' ? mb_substr(trim($rawSearch), 0, 100) : null;

        $sessions = ViewSession::query()
            ->with([
                'campaign:id,title',
                'voter:id,full_name,email',
            ])
            ->whereIn('political_campaign_id', $campaignIds)
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($voterSearch, fn($q) => $q->whereHas('voter', fn($vq) =>
                $vq->where('full_name', 'like', '%' . $voterSearch . '%')
                   ->orWhere('email', 'like', '%' . $voterSearch . '%')
            ))
            ->orderBy('created_at')
            ->limit(20000)
            ->get();

        $referralEarnings = ReferralEarning::query()
            ->with([
                'referrer:id,full_name,email',
                'viewSession:id,uuid,political_campaign_id,voter_id,status,payment_status,paid_at,created_at,processor_selected,processor_executed,processor_reference,processor_fee',
                'viewSession.campaign:id,title',
            ])
            ->whereHas('viewSession', function ($query) use ($campaignIds) {
                $query->whereIn('political_campaign_id', $campaignIds);
            })
            ->when($from, fn($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn($q) => $q->whereDate('created_at', '<=', $to))
            ->when($voterSearch, fn($q) => $q->whereHas('referrer', fn($vq) =>
                $vq->where('full_name', 'like', '%' . $voterSearch . '%')
                   ->orWhere('email', 'like', '%' . $voterSearch . '%')
            ))
            ->orderBy('created_at')
            ->limit(20000)
            ->get();

        $isTruncated = $sessions->count() >= 20000 || $referralEarnings->count() >= 20000;
        $filename = 'voter-accounting-' . $activePaymentMode . '-' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($sessions, $referralEarnings, $activePaymentMode, $isTruncated) {
            $output = fopen('php://output', 'w');

            if ($isTruncated) {
                fputcsv($output, ['WARNING: Export truncated at 20,000 rows per data type. Apply date or voter filters to narrow results.']);
                fputcsv($output, []);
            }

            fputcsv($output, [
                'Generated At',
                now()->toDateTimeString(),
                'Payment Mode',
                $activePaymentMode,
            ]);
            fputcsv($output, []);

            fputcsv($output, [
                'Record Type',
                'Record ID',
                'Created At',
                'Accounting Month',
                'Voter ID',
                'Voter Name',
                'Voter Email',
                'Processor Selected',
                'Processor Executed',
                'Processor Reference',
                'Processor Fee',
                'Campaign ID',
                'Campaign Title',
                'Session ID',
                'Session UUID',
                'Session Status',
                'Session Payment Status',
                'Voter Payout Amount',
                'Referral Commission Amount',
                'Referral Type',
                'Paid',
                'Paid At',
                'Referrer Voter ID',
                'Referrer Politician ID',
            ]);

            foreach ($sessions as $session) {
                $voter = $session->voter;
                $monthDate = $session->completed_at ?? $session->created_at;

                fputcsv($output, [
                    'view_session',
                    $session->id,
                    optional($session->created_at)->toDateTimeString(),
                    optional($monthDate)->format('Y-m'),
                    $session->voter_id,
                    $voter?->full_name,
                    $voter?->email,
                    $session->processor_selected,
                    $session->processor_executed,
                    $session->processor_reference,
                    number_format((float) ($session->processor_fee ?? 0), 2, '.', ''),
                    $session->political_campaign_id,
                    optional($session->campaign)->title,
                    $session->id,
                    $session->uuid,
                    (string) ($session->getRawOriginal('status') ?? ''),
                    (string) ($session->getRawOriginal('payment_status') ?? ''),
                    number_format((float) ($session->voter_payout_amount ?? 0), 2, '.', ''),
                    number_format((float) ($session->referral_commission ?? 0), 2, '.', ''),
                    '',
                    $session->paid_at ? 'Yes' : 'No',
                    optional($session->paid_at)->toDateTimeString(),
                    '',
                    '',
                ]);
            }

            foreach ($referralEarnings as $earning) {
                $referrer = $earning->referrer;
                $session = $earning->viewSession;
                $monthDate = $earning->paid_at ?? $earning->created_at;

                fputcsv($output, [
                    'referral_earning',
                    $earning->id,
                    optional($earning->created_at)->toDateTimeString(),
                    optional($monthDate)->format('Y-m'),
                    $earning->referrer_voter_id,
                    $referrer?->full_name,
                    $referrer?->email,
                    $session?->processor_selected,
                    $session?->processor_executed,
                    $session?->processor_reference,
                    number_format((float) ($session?->processor_fee ?? 0), 2, '.', ''),
                    $session?->political_campaign_id,
                    optional($session?->campaign)->title,
                    $session?->id,
                    $session?->uuid,
                    $session ? (string) ($session->getRawOriginal('status') ?? '') : '',
                    $session ? (string) ($session->getRawOriginal('payment_status') ?? '') : '',
                    '',
                    number_format((float) ($earning->commission_amount ?? 0), 2, '.', ''),
                    $earning->referral_type,
                    $earning->paid ? 'Yes' : 'No',
                    optional($earning->paid_at)->toDateTimeString(),
                    $earning->referrer_voter_id,
                    $earning->referrer_politician_id,
                ]);
            }

            $monthly = [];
            foreach ($sessions as $session) {
                $monthDate = $session->completed_at ?? $session->created_at;
                $month = optional($monthDate)->format('Y-m') ?? 'unknown';
                $voterKey = (string) ($session->voter_id ?? 0);
                $key = $month . '|' . $voterKey;

                if (!isset($monthly[$key])) {
                    $monthly[$key] = [
                        'month' => $month,
                        'voter_id' => $session->voter_id,
                        'voter_name' => optional($session->voter)->full_name,
                        'session_payout_total' => 0.0,
                        'session_referral_total' => 0.0,
                        'referral_earning_total' => 0.0,
                        'paid_records' => 0,
                        'held_records' => 0,
                    ];
                }

                $monthly[$key]['session_payout_total'] += (float) ($session->voter_payout_amount ?? 0);
                $monthly[$key]['session_referral_total'] += (float) ($session->referral_commission ?? 0);
                if ((string) ($session->getRawOriginal('payment_status') ?? '') === 'paid') {
                    $monthly[$key]['paid_records']++;
                } else {
                    $monthly[$key]['held_records']++;
                }
            }

            foreach ($referralEarnings as $earning) {
                $monthDate = $earning->paid_at ?? $earning->created_at;
                $month = optional($monthDate)->format('Y-m') ?? 'unknown';
                $voterKey = (string) ($earning->referrer_voter_id ?? 0);
                $key = $month . '|' . $voterKey;

                if (!isset($monthly[$key])) {
                    $monthly[$key] = [
                        'month' => $month,
                        'voter_id' => $earning->referrer_voter_id,
                        'voter_name' => optional($earning->referrer)->full_name,
                        'session_payout_total' => 0.0,
                        'session_referral_total' => 0.0,
                        'referral_earning_total' => 0.0,
                        'paid_records' => 0,
                        'held_records' => 0,
                    ];
                }

                $monthly[$key]['referral_earning_total'] += (float) ($earning->commission_amount ?? 0);
                if ($earning->paid) {
                    $monthly[$key]['paid_records']++;
                } else {
                    $monthly[$key]['held_records']++;
                }
            }

            ksort($monthly);
            fputcsv($output, []);
            fputcsv($output, ['Monthly Rollup']);
            fputcsv($output, [
                'Month',
                'Voter ID',
                'Voter Name',
                'Session Payout Total',
                'Session Referral Total',
                'Referral Earning Total',
                'Paid Records',
                'Held Records',
            ]);

            foreach ($monthly as $row) {
                fputcsv($output, [
                    $row['month'],
                    $row['voter_id'],
                    $row['voter_name'],
                    number_format((float) $row['session_payout_total'], 2, '.', ''),
                    number_format((float) $row['session_referral_total'], 2, '.', ''),
                    number_format((float) $row['referral_earning_total'], 2, '.', ''),
                    $row['paid_records'],
                    $row['held_records'],
                ]);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * Show revenue report.
     */
    public function revenueReport()
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIds($activePaymentMode);
        $stats = $this->buildAnalyticsStats($campaignIds, $activePaymentMode);

        $revenue = [
            'total' => $stats['gross_revenue'],
            'payouts' => $stats['total_payouts'],
            'referrals' => $stats['total_referrals'],
            'profit' => $stats['net_revenue'],
            'margin_percent' => $stats['margin_percent'],
        ];

        return view('standalone.admin.reports-revenue', compact('revenue', 'activePaymentMode'));
    }

    /**
     * Show engagement report.
     */
    public function engagementReport(Request $request)
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIds($activePaymentMode);

        $days = (int) $request->query('days', 30);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $questionStatus = (string) $request->query('question_status', 'all');
        $allowedStatuses = ['all', 'open', 'in_review', 'resolved', 'dismissed'];
        if (!in_array($questionStatus, $allowedStatuses, true)) {
            $questionStatus = 'all';
        }

        $publicVisibility = (string) $request->query('public_visibility', 'all');
        $allowedVisibility = ['all', 'pending', 'approved', 'rejected'];
        if (!in_array($publicVisibility, $allowedVisibility, true)) {
            $publicVisibility = 'all';
        }

        $since = now()->subDays($days);
        $sessionQuery = ViewSession::whereIn('political_campaign_id', $campaignIds);
        $completedSessionsQuery = (clone $sessionQuery)->where('status', 'completed');

        $engagement = [
            'total_sessions'      => (clone $sessionQuery)->count(),
            'completed_sessions'  => (clone $completedSessionsQuery)->count(),
            'flagged_sessions'    => (clone $sessionQuery)->where('fraud_score', '>', 50)->count(),
            'avg_watch_percent'   => (clone $completedSessionsQuery)->avg('completion_percentage') ?? 0,
            'survey_responses'    => EngagementSurveyResponse::whereIn('campaign_id', $campaignIds)->count(),
            'voter_questions'     => VoterWatchReport::query()->messages()->whereIn('campaign_id', $campaignIds)->count(),
            'survey_last_window'  => EngagementSurveyResponse::whereIn('campaign_id', $campaignIds)
                ->where('created_at', '>=', $since)
                ->count(),
            'questions_last_window' => VoterWatchReport::query()->messages()
                ->whereIn('campaign_id', $campaignIds)
                ->where('created_at', '>=', $since)
                ->count(),
        ];

        $questionBaseQuery = VoterWatchReport::query()
            ->messages()
            ->whereIn('campaign_id', $campaignIds);

        $questionStatusCounts = (clone $questionBaseQuery)
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $questionsQuery = (clone $questionBaseQuery)->with([
            'campaign:id,title',
            'voter.user:id,name',
        ]);

        if ($questionStatus !== 'all') {
            $questionsQuery->where('status', $questionStatus);
        }

        if ($publicVisibility !== 'all') {
            $questionsQuery->where('public_visibility', $publicVisibility);
        }

        $recentQuestions = $questionsQuery
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $surveyBaseQuery = EngagementSurveyResponse::query()
            ->whereIn('engagement_survey_responses.campaign_id', $campaignIds)
            ->where('engagement_survey_responses.created_at', '>=', $since);

        $surveyOptionBreakdown = (clone $surveyBaseQuery)
            ->select('response_value', DB::raw('COUNT(*) as total'))
            ->groupBy('response_value')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $surveyCampaignBreakdown = (clone $surveyBaseQuery)
            ->join('political_campaigns', 'political_campaigns.id', '=', 'engagement_survey_responses.campaign_id')
            ->select(
                'engagement_survey_responses.campaign_id',
                'political_campaigns.title',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('engagement_survey_responses.campaign_id', 'political_campaigns.title')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        $questionStats = [
            'all' => (clone $questionBaseQuery)->count(),
            'open' => (int) ($questionStatusCounts->get('open') ?? 0),
            'in_review' => (int) ($questionStatusCounts->get('in_review') ?? 0),
            'resolved' => (int) ($questionStatusCounts->get('resolved') ?? 0),
            'dismissed' => (int) ($questionStatusCounts->get('dismissed') ?? 0),
            'pending_public' => (clone $questionBaseQuery)->where('public_visibility', 'pending')->count(),
            'approved_public' => (clone $questionBaseQuery)->where('public_visibility', 'approved')->count(),
        ];

        return view('standalone.admin.reports-engagement', compact(
            'engagement',
            'activePaymentMode',
            'questionStatus',
            'publicVisibility',
            'questionStats',
            'recentQuestions',
            'days',
            'surveyOptionBreakdown',
            'surveyCampaignBreakdown'
        ));
    }

    public function moderateQuestion(Request $request, VoterWatchReport $report)
    {
        $validated = $request->validate([
            'visibility_action' => ['required', 'in:approve,reject'],
        ]);

        abort_unless($report->type === 'message', 422, 'Only voter questions can be moderated here.');

        if ($validated['visibility_action'] === 'approve') {
            $report->public_visibility = 'approved';
            $report->is_public_board = true;
            $report->published_by = auth()->id();
            $report->published_at = now();
            $report->save();

            return back()->with('success', 'Question approved for the public board.');
        }

        $report->public_visibility = 'rejected';
        $report->is_public_board = false;
        $report->published_by = auth()->id();
        $report->published_at = now();
        $report->save();

        return back()->with('success', 'Question removed from the public board.');
    }

    // ── Settings ────────────────────────────────────────────────────────────

    /**
     * Show system settings (including SMTP / Mailgun email configuration).
     */
    public function settings()
    {
        $smtp = [];
        foreach (self::ALL_ENV_KEYS as $key) {
            $smtp[$key] = env($key, '');
        }

        $adminTwoFactorEnforced = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        $registrationOpen = filter_var(
            PlatformSettingsService::get('registration_open', null, true),
            FILTER_VALIDATE_BOOLEAN
        );

        return view('standalone.admin.settings', compact('smtp', 'adminTwoFactorEnforced', 'registrationOpen'));
    }

    /**
     * Update global security settings controlled from the admin settings page.
     */
    public function updateSecuritySettings(Request $request)
    {
        $validated = $request->validate([
            'admin_2fa_enforced' => ['required', 'boolean'],
            'registration_open'  => ['required', 'boolean'],
        ]);

        // ── Admin 2FA policy ─────────────────────────────────────────────────
        $newTwoFa = (bool) $validated['admin_2fa_enforced'];
        $currentTwoFa = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        PlatformSettingsService::set('admin_2fa_enforced', $newTwoFa, [
            'description' => 'Global policy toggle requiring TOTP for all admin logins.',
            'category' => 'general',
        ]);

        Log::info('Admin security policy updated', [
            'admin_id' => auth()->id(),
            'key' => 'admin_2fa_enforced',
            'old_value' => $currentTwoFa,
            'new_value' => $newTwoFa,
        ]);

        AdminSecurityAuditLog::record(
            $request->user(),
            'policy.admin_2fa.updated',
            [
                'old_value' => $currentTwoFa,
                'new_value' => $newTwoFa,
            ],
            $request
        );

        // ── Registration open/closed toggle ──────────────────────────────────
        $newRegistration = (bool) $validated['registration_open'];
        $currentRegistration = filter_var(
            PlatformSettingsService::get('registration_open', null, true),
            FILTER_VALIDATE_BOOLEAN
        );

        PlatformSettingsService::set('registration_open', $newRegistration, [
            'description' => 'Controls whether new user registrations are accepted.',
            'category' => 'general',
        ]);

        Log::info('Registration policy updated', [
            'admin_id' => auth()->id(),
            'old_value' => $currentRegistration,
            'new_value' => $newRegistration,
        ]);

        AdminSecurityAuditLog::record(
            $request->user(),
            'policy.registration.updated',
            [
                'old_value' => $currentRegistration,
                'new_value' => $newRegistration,
            ],
            $request
        );

        return back()->with('success', 'Security policy updated successfully.');
    }

    /**
     * Update system settings.
     *
     * Persists mail values to the .env file and refreshes the config cache.
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'MAIL_MAILER'        => ['required', 'string', 'in:smtp,mailgun,sendmail,log,array'],
            'MAIL_HOST'          => ['required_if:MAIL_MAILER,smtp', 'nullable', 'string'],
            'MAIL_PORT'          => ['required_if:MAIL_MAILER,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'MAIL_USERNAME'      => ['nullable', 'string'],
            'MAIL_PASSWORD'      => ['nullable', 'string'],
            'MAIL_ENCRYPTION'    => ['nullable', 'string', 'in:ssl,tls,starttls,'],
            'MAIL_FROM_ADDRESS'  => ['required', 'email'],
            'MAIL_FROM_NAME'     => ['required', 'string', 'max:100'],
            'MAILGUN_DOMAIN'     => ['required_if:MAIL_MAILER,mailgun', 'nullable', 'string'],
            'MAILGUN_SECRET'     => ['required_if:MAIL_MAILER,mailgun', 'nullable', 'string'],
            'MAILGUN_ENDPOINT'   => ['nullable', 'string', 'in:api.mailgun.net,api.eu.mailgun.net,'],
        ]);

        foreach (self::ALL_ENV_KEYS as $key) {
            $value = (string) ($request->input($key) ?? '');
            $this->setEnvValue($key, $value);
        }

        // Clear config cache so new values take effect immediately
        Artisan::call('config:clear');

        return back()->with('success', 'Email settings saved successfully.');
    }

    /**
     * Send a test email to the currently authenticated admin.
     */
    public function testEmail(Request $request)
    {
        $to = auth()->user()->email;

        try {
            Mail::raw('This is a test email from U9itus Admin Panel. Your SMTP configuration is working correctly.', function ($msg) use ($to) {
                $msg->to($to)
                    ->subject('U9itus – SMTP Test Email');
            });

            return response()->json(['success' => true, 'message' => "Test email sent to {$to}."]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Update admin password from settings page.
     */
    public function updatePassword(Request $request)
    {
        $request->validateWithBag('updatePassword', [
            'current_password'          => ['required', 'current_password'],
            'new_password'              => ['required', 'min:8', 'confirmed'],
            'new_password_confirmation' => ['required'],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
            'new_password.min'                  => 'The new password must be at least 8 characters.',
            'new_password.confirmed'            => 'The password confirmation does not match.',
        ]);

        $user = auth()->user();
        $user->forceFill([
            'password' => Hash::make($request->input('new_password')),
        ])->save();

        // Send notification email (non-fatal)
        try {
            Mail::to($user->email)
                ->send(new \App\Mail\AdminPasswordResetMail($user));
        } catch (\Exception $e) {
            // Log but don't fail
            \Log::warning('Failed to send password change notification: ' . $e->getMessage());
        }

        return back()->with('password_success', 'Password updated successfully.');
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /**
     * Write or update a key=value pair in the .env file.
     */
    private function setEnvValue(string $key, string $value): void
    {
        $envPath = base_path('.env');

        if (! file_exists($envPath)) {
            return;
        }

        $contents = file_get_contents($envPath);

        // Wrap values with spaces or special chars in double quotes
        $needsQuotes = preg_match('/\s|[#"\'\\\\]/', $value);
        $formatted   = $needsQuotes ? '"' . addslashes($value) . '"' : ($value === '' ? '""' : $value);

        $pattern     = "/^{$key}=.*/m";
        $replacement = "{$key}={$formatted}";

        if (preg_match($pattern, $contents)) {
            $contents = preg_replace($pattern, $replacement, $contents);
        } else {
            $contents .= "\n{$replacement}";
        }

        file_put_contents($envPath, $contents);
    }

    // ── Platform Settings ─────────────────────────────────────────────

    /**
     * Show the platform settings dashboard (pricing, commissions, thresholds).
     */
    public function platformSettings()
    {
        $service = new \App\Services\PlatformSettingsService();
        
        // Get all active settings grouped by category
        $settingsByCategory = \App\Models\PlatformSetting::active()
            ->orderBy('category')
            ->orderBy('key')
            ->get()
            ->groupBy('category');

        // Get current effective values for key settings
        $currentValues = [
            'revenue_per_view' => $service->get('revenue_per_view'),
            'viewer_payout_per_view' => $service->get('viewer_payout_per_view'),
            'referral_commission_percent' => $service->get('referral_commission_percent'),
            'procurement_commission_percent' => $service->get('procurement_commission_percent'),
            'min_payout_amount' => $service->get('min_payout_amount'),
            'fraud_max_views_per_day' => $service->get('fraud_max_views_per_day'),
            'fraud_payout_hold_hours' => $service->get('fraud_payout_hold_hours'),
        ];

        // Get active promotions
        $activePromotions = \App\Models\PlatformSetting::active()
            ->whereNotNull('effective_until')
            ->orderBy('effective_until')
            ->get();

        return view('standalone.admin.platform-settings', compact(
            'settingsByCategory',
            'currentValues',
            'activePromotions'
        ));
    }

    /**
     * Update a platform setting.
     */
    public function updatePlatformSetting(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'value' => 'required',
            'description' => 'nullable|string|max:500',
            'category' => 'nullable|string|in:pricing,fraud,video,referral,general,analytics',
            'user_tier' => 'nullable|string|in:early_adopter,regular',
            'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date|after:effective_from',
            'metadata' => 'nullable|array',
        ]);

        $options = array_filter([
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'user_tier' => $validated['user_tier'] ?? null,
            'effective_from' => isset($validated['effective_from']) ? \Carbon\Carbon::parse($validated['effective_from']) : null,
            'effective_until' => isset($validated['effective_until']) ? \Carbon\Carbon::parse($validated['effective_until']) : null,
            'metadata' => $validated['metadata'] ?? null,
        ], fn($v) => $v !== null);

        \App\Services\PlatformSettingsService::set(
            $validated['key'],
            $validated['value'],
            $options
        );

        Log::info('Admin updated platform setting', [
            'admin_id' => auth()->id(),
            'key' => $validated['key'],
            'value' => $validated['value'],
            'user_tier' => $validated['user_tier'] ?? null,
        ]);

        return back()->with('success', 'Platform setting updated successfully.');
    }

    /**
     * Delete a platform setting (revert to config default).
     */
    public function deletePlatformSetting(Request $request)
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255',
            'user_tier' => 'nullable|string|in:early_adopter,regular',
        ]);

        $deleted = \App\Services\PlatformSettingsService::delete(
            $validated['key'],
            $validated['user_tier'] ?? null
        );

        if ($deleted) {
            Log::info('Admin deleted platform setting', [
                'admin_id' => auth()->id(),
                'key' => $validated['key'],
                'user_tier' => $validated['user_tier'] ?? null,
            ]);

            return back()->with('success', 'Setting deleted — reverted to default value.');
        }

        return back()->withErrors(['key' => 'Setting not found.']);
    }

    /**
     * Clear platform settings cache.
     */
    public function clearSettingsCache()
    {
        \App\Services\PlatformSettingsService::clearAllCache();

        Log::info('Admin cleared platform settings cache', [
            'admin_id' => auth()->id(),
        ]);

        return back()->with('success', 'Settings cache cleared successfully.');
    }

}
