<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Http\Controllers\Concerns\PaymentModeFilterable;
use App\Http\Controllers\Controller;
use App\Jobs\MatchPoliticianToElectionData;
use App\Jobs\ProcessOcrCandidateImportJob;
use App\Mail\CampaignReactivatedMail;
use App\Mail\AccountUnsuspendedMail;
use App\Models\CandidateMatchReview;
use App\Models\DistrictLookupSearch;
use App\Models\EngagementSurveyResponse;
use App\Services\ReverbBroadcastService;
use App\Services\PoliticianElectionMatcher;
use App\Mail\KycApprovedMail;
use App\Mail\KycRejectedMail;
use App\Models\AdminSecurityAuditLog;
use App\Models\CampaignAuditLog;
use App\Models\CampaignTransaction;
use App\Models\EmailTemplate;
use App\Models\OnboardingHandoffEvent;
use App\Models\PoliticalCampaign;
use App\Models\PoliticianCredit;
use App\Models\Politician;
use App\Models\ReferralEarning;
use App\Models\PayoutRun;
use App\Models\PayoutRunSkippedItem;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\VoterWatchReport;
use App\Models\Voter;
use App\Notifications\CampaignStatusChangedNotification;
use App\Notifications\SystemAnnouncementNotification;
use App\Services\AdminTwoFactorService;
use App\Services\CampaignBillingService;
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
use Illuminate\Support\Facades\Storage;

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
     * Store a campaign video on the configured disk and return its public URL.
     */
    private function storeCampaignVideoAndGetUrl(UploadedFile $video, PoliticalCampaign $campaign): ?string
    {
        $disk = (string) config('filesystems.default', 'local');
        $disks = (array) config('filesystems.disks', []);

        if (! array_key_exists($disk, $disks)) {
            Log::error('Admin campaign video upload failed: filesystem disk is not configured', [
                'campaign_id' => $campaign->id,
                'disk' => $disk,
            ]);

            return null;
        }

        try {
            $path = $video->store("campaigns/{$campaign->id}/video", $disk);

            if (! is_string($path) || $path === '') {
                Log::error('Admin campaign video upload failed: storage returned empty path', [
                    'campaign_id' => $campaign->id,
                    'disk' => $disk,
                ]);

                return null;
            }

            return Storage::disk($disk)->url($path);
        } catch (\Throwable $e) {
            Log::error('Admin campaign video upload failed with exception', [
                'campaign_id' => $campaign->id,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Campaign ids that have transaction activity in the active payment mode.
     *
     * Credit-purchase transactions carry payment_mode in their JSON metadata but are
     * recorded with campaign_id = null. Derive campaign IDs by finding which politicians
     * made purchases in the given mode, then returning all their campaign IDs.
     */
    private function modeScopedCampaignIds(string $mode)
    {
        $politicianIds = CampaignTransaction::query()
            ->select('politician_id')
            ->whereNotNull('politician_id')
            ->where('metadata->payment_mode', $mode)
            ->distinct();

        return PoliticalCampaign::query()
            ->select('id')
            ->whereIn('politician_id', $politicianIds)
            ->distinct();
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
            'total_views'       => (clone $completedViewQuery)->count(),
            'total_revenue'     => (clone $completedViewQuery)->sum('platform_revenue') ?? 0,
            'total_payouts'     => (clone $completedViewQuery)->sum('voter_payout_amount') ?? 0,
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

        return view('standalone.admin.campaigns-pending', compact('campaigns'));
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

        return view('standalone.admin.campaigns-running', compact('campaigns', 'summary'));
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
     * List all users.
     */
    public function users(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $role = (string) $request->query('role', '');
        $kyc = (string) $request->query('kyc', '');
        $accountStatus = (string) $request->query('account_status', '');
        $authenticUserVerifier = (string) $request->query('authentic_user_verifier', '');

        $allowedRoles = ['admin', 'politician', 'voter'];
        $allowedKycStatuses = ['approved', 'pending', 'rejected'];
        $allowedAccountStatuses = ['active', 'unverified', 'suspended'];
        $allowedAuthenticUserVerifierStatuses = ['pending', 'completed'];

        $usersQuery = User::query()->with(['politician', 'voter']);

        if ($search !== '') {
            $likeSearch = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';

            $usersQuery->where(function (Builder $query) use ($search, $likeSearch) {
                $query->where('name', 'like', $likeSearch)
                    ->orWhere('email', 'like', $likeSearch)
                    ->orWhere('phone', 'like', $likeSearch)
                    ->orWhereHas('politician', function (Builder $politicianQuery) use ($likeSearch) {
                        $politicianQuery->where('full_name', 'like', $likeSearch)
                            ->orWhere('political_office', 'like', $likeSearch)
                            ->orWhere('city', 'like', $likeSearch)
                            ->orWhere('state', 'like', $likeSearch);
                    })
                    ->orWhereHas('voter', function (Builder $voterQuery) use ($likeSearch) {
                        $voterQuery->where('email', 'like', $likeSearch)
                            ->orWhere('city', 'like', $likeSearch)
                            ->orWhere('state', 'like', $likeSearch);
                    });

                if (ctype_digit($search)) {
                    $query->orWhere('id', (int) $search);
                }
            });
        }

        if (in_array($role, $allowedRoles, true)) {
            $usersQuery->where('user_type', $role);
        }

        if (in_array($kyc, $allowedKycStatuses, true)) {
            $usersQuery->where('kyc_status', $kyc);
        }

        if (in_array($accountStatus, $allowedAccountStatuses, true)) {
            $usersQuery->where(function (Builder $query) use ($accountStatus) {
                if ($accountStatus === 'suspended') {
                    $query->whereNotNull('suspended_at');
                    return;
                }

                if ($accountStatus === 'active') {
                    $query->whereNull('suspended_at')
                        ->whereNotNull('email_verified_at');
                    return;
                }

                $query->whereNull('suspended_at')
                    ->whereNull('email_verified_at');
            });
        }

        if (in_array($authenticUserVerifier, $allowedAuthenticUserVerifierStatuses, true)) {
            // Authentic User Verifier applies to legacy voter accounts migrating to Stripe Connect.
            $usersQuery->where('user_type', 'voter')
                ->whereHas('voter', function (Builder $voterQuery) use ($authenticUserVerifier) {
                    $voterQuery->whereHas('user', function (Builder $legacyUser) {
                        $legacyUser->where('user_type', 'voter')
                            ->where(function (Builder $legacy) {
                                $legacy->whereNotNull('kyc_document_path')
                                    ->orWhereNotNull('idme_verified_at')
                                    ->orWhereIn('kyc_status', ['pending', 'approved', 'rejected']);
                            });
                    });

                    if ($authenticUserVerifier === 'pending') {
                        $voterQuery->where(function (Builder $stripe) {
                            $stripe->whereNull('stripe_account_id')
                                ->orWhere('stripe_account_id', '')
                                ->orWhereNull('stripe_account_status')
                                ->orWhere('stripe_account_status', '!=', 'active');
                        });
                        return;
                    }

                    $voterQuery->whereNotNull('stripe_account_id')
                        ->where('stripe_account_id', '!=', '')
                        ->where('stripe_account_status', 'active');
                });
        }

        $users = $usersQuery
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('standalone.admin.users', compact('users'));
    }

    /**
     * Apply a bulk action to selected users from the users index page.
     */
    public function bulkUserAction(Request $request)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:suspend,unsuspend,kyc_approve,kyc_reject'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $action = (string) $validated['action'];
        $userIds = collect($validated['user_ids'])
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        $users = User::query()
            ->with(['politician', 'voter'])
            ->whereIn('id', $userIds)
            ->get();

        if ($users->isEmpty()) {
            return back()->withErrors(['error' => 'No users were selected.']);
        }

        $updated = 0;
        $skippedAdmins = 0;
        $skippedVoters = 0;
        $reviewedAt = now();

        foreach ($users as $user) {
            if ($user->user_type === 'admin' && in_array($action, ['suspend', 'kyc_approve', 'kyc_reject'], true)) {
                $skippedAdmins++;
                continue;
            }

            // Voters use Stripe for identity verification; skip manual KYC bulk actions.
            if ($user->user_type === 'voter' && in_array($action, ['kyc_approve', 'kyc_reject'], true)) {
                $skippedVoters++;
                continue;
            }

            if ($action === 'suspend') {
                if ($user->isSuspended()) {
                    continue;
                }

                $user->update([
                    'suspended_at' => now(),
                    'suspension_reason' => 'Suspended by administrator (bulk action).',
                ]);

                if ($user->voter) {
                    $user->voter->update(['is_active' => false]);
                }
                if ($user->politician) {
                    $user->politician->update(['is_active' => false]);
                }

                $updated++;
                continue;
            }

            if ($action === 'unsuspend') {
                if (! $user->isSuspended()) {
                    continue;
                }

                $user->update([
                    'suspended_at' => null,
                    'suspension_reason' => null,
                ]);

                if ($user->voter) {
                    $user->voter->update(['is_active' => true]);
                }
                if ($user->politician) {
                    $user->politician->update(['is_active' => true]);
                }

                $updated++;
                continue;
            }

            if ($action === 'kyc_approve') {
                $user->update([
                    'kyc_status' => 'approved',
                    'kyc_reviewed_at' => $reviewedAt,
                    'kyc_reviewer_id' => auth()->id(),
                    'kyc_rejection_reason' => null,
                ]);

                $updated++;
                continue;
            }

            $user->update([
                'kyc_status' => 'rejected',
                'kyc_reviewed_at' => $reviewedAt,
                'kyc_reviewer_id' => auth()->id(),
                'kyc_rejection_reason' => 'Rejected by administrator (bulk action).',
            ]);

            $updated++;
        }

        $labels = [
            'suspend' => 'suspended',
            'unsuspend' => 'unsuspended',
            'kyc_approve' => 'KYC approved for',
            'kyc_reject' => 'KYC rejected for',
        ];

        if ($updated === 0) {
            $noneAppliedMessage = 'No selected users were eligible for that action.';

            if ($skippedAdmins > 0) {
                $noneAppliedMessage .= ' Admin accounts were skipped.';
            }
            if ($skippedVoters > 0) {
                $noneAppliedMessage .= ' Voter accounts were skipped (use Stripe for voter verification).';
            }

            return back()->withErrors(['error' => $noneAppliedMessage]);
        }

        $message = $updated . ' user(s) ' . $labels[$action] . '.';

        if ($skippedAdmins > 0) {
            $message .= ' ' . $skippedAdmins . ' admin account(s) skipped.';
        }
        if ($skippedVoters > 0) {
            $message .= ' ' . $skippedVoters . ' voter account(s) skipped (Stripe-verified).';
        }

        return back()->with('success', $message);
    }

    /**
     * Show user details.
     */
    public function showUser($userId)
    {
        $user = User::with(['politician', 'voter.viewSessions' => function ($q) {
            $q->latest()->take(10);
        }])->findOrFail($userId);

        return view('standalone.admin.user-details', compact('user'));
    }

    /**
     * Show pending candidate match reviews generated by automatic matching.
     */
    public function candidateMatchReviews(Request $request)
    {
        $statusFilter = $request->query('status', 'pending');
        $allowedStatuses = [
            CandidateMatchReview::STATUS_PENDING,
            CandidateMatchReview::STATUS_APPROVED,
            CandidateMatchReview::STATUS_REJECTED,
        ];

        $query = CandidateMatchReview::with(['politician.user', 'candidateRecord'])->latest();

        if (in_array($statusFilter, $allowedStatuses, true)) {
            $query->where('status', $statusFilter);
        }

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where(function ($q) use ($search) {
                $q->whereHas('politician', function ($pq) use ($search) {
                    $pq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('political_office', 'like', "%{$search}%");
                })->orWhereHas('candidateRecord', function ($cq) use ($search) {
                    $cq->where('full_name', 'like', "%{$search}%")
                        ->orWhere('political_office', 'like', "%{$search}%");
                });
            });
        }

        $reviews = $query->paginate(30)->withQueryString();

        $stats = [
            'pending' => CandidateMatchReview::where('status', CandidateMatchReview::STATUS_PENDING)->count(),
            'approved' => CandidateMatchReview::where('status', CandidateMatchReview::STATUS_APPROVED)->count(),
            'rejected' => CandidateMatchReview::where('status', CandidateMatchReview::STATUS_REJECTED)->count(),
        ];

        return view('standalone.admin.candidate-match-reviews', compact('reviews', 'stats', 'statusFilter'));
    }

    /**
     * Bulk approve or reject pending candidate match reviews.
     */
    public function bulkCandidateMatchAction(Request $request, PoliticianElectionMatcher $matcher)
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,reject'],
            'review_ids' => ['required', 'array', 'min:1'],
            'review_ids.*' => ['integer', 'exists:candidate_match_reviews,id'],
        ]);

        $action = (string) $validated['action'];
        $reviewIds = collect($validated['review_ids'])
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values();

        $reviews = CandidateMatchReview::whereIn('id', $reviewIds)
            ->where('status', CandidateMatchReview::STATUS_PENDING)
            ->get();

        if ($reviews->isEmpty()) {
            return back()->withErrors(['error' => 'No pending reviews were selected.']);
        }

        $updated = 0;
        $admin = auth()->user();
        $label = $action === 'approve' ? 'Bulk approved by admin' : 'Bulk rejected by admin';

        foreach ($reviews as $review) {
            if ($action === 'approve') {
                $matcher->approveReview($review, $admin, $label);
            } else {
                $matcher->rejectReview($review, $admin, $label);
            }
            $updated++;
        }

        $noun = $updated === 1 ? 'match' : 'matches';

        return back()->with('success', "Bulk {$action}d {$updated} candidate {$noun}.");
    }

    /**
     * Run election candidate import from Admin UI.
     */
    public function importElectionCandidates(Request $request)
    {
        $request->validate([
            'source' => ['required', 'string', 'max:64'],
            'file' => ['nullable', 'string', 'max:255', 'required_without:file_upload'],
            'file_upload' => ['nullable', 'file', 'mimes:json,txt', 'max:10240', 'required_without:file'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $importFile = (string) $request->input('file', '');

        if ($request->hasFile('file_upload')) {
            $upload = $request->file('file_upload');
            $safeName = 'candidate-import-' . now()->format('Ymd-His') . '-' . uniqid('', true) . '.json';
            $storedRelative = $upload->storeAs('imports/uploads', $safeName, 'local');
            $importFile = Storage::disk('local')->path((string) $storedRelative);
        }

        $args = [
            '--source' => (string) $request->input('source'),
            '--file' => $importFile,
        ];

        if ($request->boolean('dry_run')) {
            $args['--dry-run'] = true;
        }

        $exitCode = Artisan::call('elections:import-candidates', $args);
        $output = trim(Artisan::output());

        if ($exitCode !== 0) {
            return back()
                ->withErrors(['error' => $output !== '' ? $output : 'Import failed.'])
                ->withInput();
        }

        return back()
            ->with('success', 'Election candidate import completed.')
            ->with('import_output', $output);
    }

    /**
     * Approve a pending candidate match review and create the identity link.
     */
    public function approveCandidateMatch(CandidateMatchReview $review, PoliticianElectionMatcher $matcher)
    {
        if ($review->status !== CandidateMatchReview::STATUS_PENDING) {
            return back()->withErrors(['error' => 'This review has already been resolved.']);
        }

        $matcher->approveReview($review, auth()->user(), 'Approved by admin');

        return back()->with('success', 'Candidate match approved and linked.');
    }

    /**
     * Reject a pending candidate match review.
     */
    public function rejectCandidateMatch(Request $request, CandidateMatchReview $review, PoliticianElectionMatcher $matcher)
    {
        if ($review->status !== CandidateMatchReview::STATUS_PENDING) {
            return back()->withErrors(['error' => 'This review has already been resolved.']);
        }

        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $matcher->rejectReview($review, auth()->user(), $request->input('reason', 'Rejected by admin'));

        return back()->with('success', 'Candidate match rejected.');
    }

    /**
     * Re-run automatic matching for a specific politician.
     */
    public function retryCandidateMatch(Politician $politician)
    {
        MatchPoliticianToElectionData::dispatch($politician->id);

        return back()->with('success', 'Re-match job queued for ' . $politician->full_name . '.');
    }

    /**
     * Suspend a user.
     */
    public function suspendUser(Request $request, $userId)
    {
        $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        $user = User::findOrFail($userId);

        if ($user->user_type === 'admin') {
            return back()->withErrors(['error' => 'Admin accounts cannot be suspended.']);
        }

        $user->update([
            'suspended_at'      => now(),
            'suspension_reason' => $request->input('reason', 'Suspended by administrator.'),
        ]);

        if ($user->voter) {
            $user->voter->update(['is_active' => false]);
        }
        if ($user->politician) {
            $user->politician->update(['is_active' => false]);
        }

        return back()->with('success', 'User "' . $user->name . '" has been suspended.');
    }

    /**
     * Unsuspend a user.
     */
    public function unsuspendUser(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        $user->update([
            'suspended_at'      => null,
            'suspension_reason' => null,
        ]);

        if ($user->voter) {
            $user->voter->update(['is_active' => true]);
        }
        if ($user->politician) {
            $user->politician->update(['is_active' => true]);
        }

        // Notify user their account access has been restored (non-fatal)
        try {
            if ($user->email) {
                Mail::to($user->email)->queue(new AccountUnsuspendedMail($user));
            }

            $dashboardRoute = match ($user->user_type) {
                'admin' => route('admin.dashboard'),
                'politician' => route('politician.dashboard'),
                'voter' => route('voter.dashboard'),
                default => route('dashboard'),
            };

            $user->notify(new SystemAnnouncementNotification(
                'Your account has been reactivated',
                'An administrator restored your account access. You can now sign in and continue using the platform.',
                $dashboardRoute,
                'Open Dashboard'
            ));
        } catch (\Throwable $e) {
            Log::warning('Failed to send account unsuspension notifications', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'User "' . $user->name . '" has been unsuspended.');
    }

    /**
     * Show fraud detection dashboard.
     */
    public function fraud()
    {
        $stats = [
            'flagged_sessions'   => ViewSession::where('fraud_score', '>', 50)->count(),
            'high_risk_sessions' => ViewSession::where('fraud_score', '>', 80)->count(),
            'total_sessions'     => ViewSession::count(),
        ];

        $flaggedSessions = ViewSession::with(['voter', 'campaign'])
            ->where('fraud_score', '>', 50)
            ->orderByDesc('fraud_score')
            ->take(10)
            ->get();

        return view('standalone.admin.fraud', compact('stats', 'flaggedSessions'));
    }

    /**
     * Show flagged views.
     */
    public function flaggedViews()
    {
        $sessions = ViewSession::with(['voter', 'campaign'])
            ->where('fraud_score', '>', 50)
            ->orderByDesc('fraud_score')
            ->paginate(30);

        return view('standalone.admin.fraud-views', compact('sessions'));
    }

    /**
     * Review a flagged view session.
     *
     * Actions:
     *   cleared   – false positive; reset fraud score, allow payout
     *   voided    – confirmed fraud; zero out payout, keep voter flagged
     *   confirmed – session looks suspicious but payout unchanged
     */
    public function reviewView(Request $request, $viewId)
    {
        $request->validate([
            'action' => ['required', 'in:cleared,voided,confirmed'],
        ]);

        $session = ViewSession::with('voter')->findOrFail($viewId);
        $action  = $request->input('action');

        $updates = [
            'reviewed_at'   => now(),
            'reviewed_by'   => auth()->id(),
            'review_action' => $action,
        ];

        if ($action === 'cleared') {
            $updates['fraud_score'] = 0;
            $updates['fraud_flags'] = [];
            if ($session->voter) {
                $session->voter->update([
                    'flagged_for_fraud' => false,
                    'trust_score'       => min(100, $session->voter->trust_score + 10),
                ]);
            }
        } elseif ($action === 'voided') {
            $updates['voter_payout_amount'] = 0;
            $updates['referral_commission']  = 0;
            $updates['payment_status']       = 'voided';
            if ($session->voter) {
                $session->voter->update(['flagged_for_fraud' => true]);
            }
        }

        $session->update($updates);

        return back()->with('success', "Session #{$viewId} marked as {$action}.");
    }

    /**
     * Clear fraud flag on a voter profile directly.
     */
    public function clearVoterFraud(Request $request, $voterId)
    {
        $voter = Voter::findOrFail($voterId);

        $voter->update([
            'flagged_for_fraud' => false,
            'trust_score'       => min(100, $voter->trust_score + 10),
        ]);

        return back()->with('success', 'Fraud flag cleared for voter.');
    }

    /**
     * Show payouts management.
     */
    public function payouts()
    {
        $unpaidStatuses = [ViewPaymentStatus::Pending->value, ViewPaymentStatus::Approved->value];

        $stats = [
            'pending_amount' => ViewSession::where('status', 'completed')
                ->whereIn('payment_status', $unpaidStatuses)->sum('voter_payout_amount') ?? 0,
            'paid_amount'    => ViewSession::where('payment_status', ViewPaymentStatus::Paid->value)->sum('voter_payout_amount') ?? 0,
            'pending_count'  => ViewSession::where('status', 'completed')
                ->whereIn('payment_status', $unpaidStatuses)->count(),
        ];

        $paypalConfigured = filled((string) config('services.paypal.client_id'))
            && filled((string) config('services.paypal.client_secret'));
        $paypalSandbox = (bool) config('services.paypal.sandbox', true);
        $cashAppConfigured = filled((string) config('services.cashapp.api_key'))
            && filled((string) config('services.cashapp.merchant_id'))
            && filled((string) config('services.cashapp.base_url'));
        $cashAppBaseUrl = (string) config('services.cashapp.base_url', '');

        $latestRun = PayoutRun::query()->latest()->first();
        $skipBuckets = [
            'below_min' => 0,
            'missing_paypal_email' => 0,
            'processor_unavailable' => 0,
        ];

        if ($latestRun) {
            $bucketRows = PayoutRunSkippedItem::query()
                ->where('payout_run_id', $latestRun->id)
                ->whereIn('reason_bucket', array_keys($skipBuckets))
                ->selectRaw('reason_bucket, COUNT(*) as count')
                ->groupBy('reason_bucket')
                ->get();

            foreach ($bucketRows as $row) {
                $skipBuckets[(string) $row->reason_bucket] = (int) $row->count;
            }
        }

        return view('standalone.admin.payouts', compact(
            'stats',
            'paypalConfigured',
            'paypalSandbox',
            'cashAppConfigured',
            'cashAppBaseUrl',
            'latestRun',
            'skipBuckets'
        ));
    }

    /**
     * Show persisted skipped payouts diagnostics by reason bucket.
     */
    public function skippedPayouts(Request $request)
    {
        $runId = $request->integer('run_id');
        $reason = (string) $request->query('reason', '');

        $selectedRun = $runId
            ? PayoutRun::query()->find($runId)
            : PayoutRun::query()->latest()->first();

        $query = PayoutRunSkippedItem::query()
            ->with(['voter.user', 'viewSession'])
            ->latest();

        if ($selectedRun) {
            $query->where('payout_run_id', $selectedRun->id);
        }

        if ($reason !== '') {
            $query->where('reason_bucket', $reason);
        }

        $items = $query->paginate(30)->withQueryString();

        $bucketSummary = ['below_min' => 0, 'missing_paypal_email' => 0, 'processor_unavailable' => 0];
        if ($selectedRun) {
            $rows = PayoutRunSkippedItem::query()
                ->where('payout_run_id', $selectedRun->id)
                ->whereIn('reason_bucket', array_keys($bucketSummary))
                ->selectRaw('reason_bucket, COUNT(*) as count')
                ->groupBy('reason_bucket')
                ->get();

            foreach ($rows as $row) {
                $bucketSummary[(string) $row->reason_bucket] = (int) $row->count;
            }
        }

        $recentRuns = PayoutRun::query()->latest()->limit(20)->get();

        return view('standalone.admin.payouts-skipped', compact(
            'items',
            'selectedRun',
            'recentRuns',
            'bucketSummary',
            'reason'
        ));
    }

    /**
     * Show pending payouts.
     */
    public function pendingPayouts(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $sessionsQuery = ViewSession::with(['voter.user', 'campaign'])
            ->where('status', 'completed')
            ->whereIn('payment_status', [ViewPaymentStatus::Pending->value, ViewPaymentStatus::Approved->value]);

        if ($search !== '') {
            $sessionsQuery->where(function ($query) use ($search) {
                $query->whereHas('campaign', function ($campaignQuery) use ($search) {
                    $campaignQuery->where('title', 'like', "%{$search}%");
                })->orWhereHas('voter', function ($voterQuery) use ($search) {
                    $voterQuery->where('email', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('email', 'like', "%{$search}%")
                                ->orWhere('name', 'like', "%{$search}%");
                        });
                })->orWhere('processor_reference', 'like', "%{$search}%")
                  ->orWhere('processor_selected', 'like', "%{$search}%");
            });
        }

        $sessions = $sessionsQuery
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return view('standalone.admin.payouts-pending', compact('sessions', 'search'));
    }

    /**
     * Process batch payouts — moves approved earnings to voters via PayPal
     * (or credits the on-platform wallet for voters without a PayPal email).
     */
    public function processBatchPayouts(Request $request)
    {
        /** @var \App\Services\PoliticalPaymentService $paymentService */
        $paymentService = app(\App\Services\PoliticalPaymentService::class);

        try {
            $results = $paymentService->processBatchPayouts(
                triggeredByAdminId: (int) $request->user()->id,
                triggerSource: 'admin',
            );
        } catch (\Exception $e) {
            Log::error('Batch payout run failed: ' . $e->getMessage());
            return back()->withErrors(['error' => 'Payout run failed: ' . $e->getMessage()]);
        }

        return back()->with('success', sprintf(
            'Batch payouts complete — %d paid ($%.2f total), %d skipped.',
            $results['processed'],
            $results['total_paid'],
            $results['skipped'],
        ));
    }

    /**
     * Execute a one-off below-minimum payout from skipped diagnostics.
     */
    public function forcePayBelowMinimum(Request $request, PayoutRunSkippedItem $skippedItem)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        /** @var \App\Models\User $admin */
        $admin = $request->user();

        if (! $admin->hasRole('admin')) {
            abort(403);
        }

        /** @var \App\Services\PoliticalPaymentService $paymentService */
        $paymentService = app(\App\Services\PoliticalPaymentService::class);

        try {
            $result = $paymentService->forcePayBelowMinimum(
                skippedItem: $skippedItem,
                adminId: (int) $admin->id,
                reason: (string) $validated['reason'],
            );

            AdminSecurityAuditLog::record(
                $admin,
                'admin.payout.force_below_minimum.success',
                [
                    'skipped_item_id' => $skippedItem->id,
                    'voter_id' => $skippedItem->voter_id,
                    'processor' => $result['processor'] ?? null,
                    'reference' => $result['reference'] ?? null,
                    'amount' => $result['amount'] ?? null,
                ],
                $request
            );
        } catch (\Throwable $e) {
            AdminSecurityAuditLog::record(
                $admin,
                'admin.payout.force_below_minimum.failed',
                [
                    'skipped_item_id' => $skippedItem->id,
                    'voter_id' => $skippedItem->voter_id,
                    'error' => $e->getMessage(),
                ],
                $request
            );

            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Exceptional payout request submitted successfully.');
    }

    /**
     * Show billing refunds management page.
     */
    public function billingRefunds(Request $request)
    {
        $activePaymentMode = $this->activePaymentMode();
        
        // Query all succeeded charge transactions
        $query = CampaignTransaction::where('transaction_type', 'charge')
            ->where('status', 'succeeded')
            ->with('politician.user')
            ->latest();

        // Apply payment mode filter
        $query = $this->applyPaymentModeFilter($query, $activePaymentMode);

        // Allow search by politician email or name
        if ($search = $request->get('search')) {
            $query->whereHas('politician', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhereHas('user', fn($q2) => $q2->where('email', 'like', "%{$search}%"));
            });
        }

        $transactions = $query->paginate(20);

        return view('standalone.admin.billing-refunds', compact('transactions', 'activePaymentMode'));
    }

    /**
     * Refund only UNUSED credits for a succeeded politician purchase transaction.
     */
    public function refundUnusedCredits(Request $request, CampaignTransaction $transaction, CampaignBillingService $billingService)
    {
        $request->validate([
            'credits_amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $admin = $request->user();
        $requestedCredits = $request->filled('credits_amount')
            ? (float) $request->input('credits_amount')
            : null;
        $reason = $request->input('reason');

        try {
            $summary = $billingService->getUnusedRefundSummary($transaction);
            $refundTx = $billingService->refundUnusedCredits(
                $transaction,
                (int) $admin->id,
                $requestedCredits,
                $reason
            );

            AdminSecurityAuditLog::record(
                $admin,
                'admin.refund.unused_credits.success',
                [
                    'purchase_transaction_id' => $transaction->id,
                    'refund_transaction_id' => $refundTx->id,
                    'requested_credits' => $requestedCredits,
                    'max_refundable_before' => $summary['refundable_credits_now'] ?? null,
                ],
                $request
            );

            $refundedCredits = (float) ($refundTx->metadata['refunded_credits_amount'] ?? 0);
            return back()->with('success', sprintf(
                'Refund created successfully. Refunded %.2f unused credits.',
                $refundedCredits
            ));
        } catch (\Throwable $e) {
            AdminSecurityAuditLog::record(
                $admin,
                'admin.refund.unused_credits.failed',
                [
                    'purchase_transaction_id' => $transaction->id,
                    'requested_credits' => $requestedCredits,
                    'error' => $e->getMessage(),
                ],
                $request
            );

            return back()->withErrors(['refund' => $e->getMessage()]);
        }
    }

    // ── KYC Management ──────────────────────────────────────────────────────

    /**
     * Show the KYC review queue.
     *
     * Lists politicians and voters with kyc_status = 'pending'.
     */
    public function kycQueue()
    {
        // Voter identity verification is handled via Stripe Connect; this queue
        // is restricted to politicians who upload government-issued ID documents.
        $users = User::with(['politician', 'voter'])
            ->where('kyc_status', 'pending')
            ->where('user_type', 'politician')
            ->latest()
            ->paginate(30);

        $stats = [
            'pending'  => User::where('kyc_status', 'pending')
                               ->where('user_type', 'politician')->count(),
            'approved' => User::where('kyc_status', 'approved')
                               ->where('user_type', 'politician')->count(),
            'rejected' => User::where('kyc_status', 'rejected')
                               ->where('user_type', 'politician')->count(),
        ];

        return view('standalone.admin.kyc', compact('users', 'stats'));
    }

    /**
     * Approve a user's KYC.
     */
    public function approveKyc(Request $request, $userId)
    {
        $user = User::with(['politician', 'voter'])->findOrFail($userId);

        // Voters use Stripe identity verification — manual admin KYC approval is not supported.
        if ($user->user_type === 'voter') {
            return back()->withErrors(['error' => 'Voter identity verification is handled via Stripe Connect and cannot be manually approved here.']);
        }

        try {
            $user->update([
                'kyc_status'      => 'approved',
                'is_verified'     => true,
                'kyc_reviewed_at' => now(),
                'kyc_reviewer_id' => auth()->id(),
            ]);
        } catch (\Exception $e) {
            // Fallback if kyc_reviewed_at or kyc_reviewer_id columns don't exist in staging
            Log::warning('KYC approval partial update (missing migration columns)', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            $user->update([
                'kyc_status'  => 'approved',
                'is_verified' => true,
            ]);
        }

        if ($user->politician) {
            $user->politician->update(['kyc_status' => 'approved', 'verified_official' => true]);
        }
        if ($user->voter) {
            $user->voter->update(['is_verified' => true]);
        }

        // Notify the user their KYC has been approved
        try {
            Mail::to($user->email)->queue(new KycApprovedMail($user));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send KYC approved email', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'KYC approved for ' . $user->name . '.');
    }

    /**
     * Reject a user's KYC.
     */
    public function rejectKyc(Request $request, $userId)
    {
        try {
            $request->validate([
                'reason' => ['nullable', 'string', 'max:500'],
            ]);

            $user = User::with(['politician', 'voter'])->findOrFail($userId);

            // Voters use Stripe identity verification — manual admin KYC rejection is not supported.
            if ($user->user_type === 'voter') {
                return back()->withErrors(['error' => 'Voter identity verification is handled via Stripe Connect and cannot be manually rejected here.']);
            }
            $reason = (string) $request->input('reason', 'Identity could not be verified.');

            $userUpdate = [
                'kyc_status' => 'rejected',
            ];

            if (Schema::hasColumn('users', 'kyc_reviewed_at')) {
                $userUpdate['kyc_reviewed_at'] = now();
            }
            if (Schema::hasColumn('users', 'kyc_reviewer_id')) {
                $userUpdate['kyc_reviewer_id'] = auth()->id();
            }
            if (Schema::hasColumn('users', 'kyc_rejection_reason')) {
                $userUpdate['kyc_rejection_reason'] = $reason;
            }

            DB::table('users')->where('id', $user->id)->update($userUpdate);
            $user->refresh();

            if ($user->politician) {
                try {
                    if (Schema::hasColumn('politicians', 'kyc_status')) {
                        DB::table('politicians')
                            ->where('id', $user->politician->id)
                            ->update(['kyc_status' => 'rejected']);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Failed to sync politician KYC rejection status', [
                        'user_id' => $user->id,
                        'politician_id' => $user->politician->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Notify the user their KYC has been rejected with the reason
            try {
                Mail::to($user->email)->queue(new KycRejectedMail($user, $reason));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to send KYC rejected email', [
                    'user_id' => $user->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::error('KYC rejection failed', [
                'user_id' => $userId,
                'admin_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.kyc.index')
                ->withErrors(['error' => 'Unable to reject KYC right now. Please try again.']);
        }

        return redirect()
            ->route('admin.kyc.index')
            ->with('success', 'KYC rejected for ' . ($user->name ?: 'user') . '.');
    }

    /**
     * View a user's KYC document (admin only).
     */
    public function viewKycDocument(User $user)
    {
        if (!$user->kyc_document_path) {
            abort(404, 'No KYC document found for this user.');
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
        $grossDeliveredRevenue = $totalNetRevenue + $totalPayouts + $totalReferrals;

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

        return [
            'total_views' => $totalViews,
            'gross_revenue' => $grossDeliveredRevenue,
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

    // ── Email Template Management ────────────────────────────────────────────

    /**
     * List all email templates.
     */
    public function emailTemplates()
    {
        $templates = EmailTemplate::orderBy('category')->orderBy('name')->get()
            ->groupBy('category');

        return view('standalone.admin.email-templates', compact('templates'));
    }

    /**
     * Show the edit form for a single email template.
     */
    public function editEmailTemplate(EmailTemplate $template)
    {
        return view('standalone.admin.email-template-edit', compact('template'));
    }

    /**
     * Persist changes to an email template.
     */
    public function updateEmailTemplate(Request $request, EmailTemplate $template)
    {
        $request->validate([
            'subject_override' => ['nullable', 'string', 'max:255'],
            'preview_text'     => ['nullable', 'string', 'max:255'],
            'body_override'    => ['nullable', 'string'],
            'is_active'        => ['boolean'],
        ]);

        $template->update([
            'subject_override' => $request->input('subject_override') ?: null,
            'preview_text'     => $request->input('preview_text') ?: null,
            'body_override'    => $request->input('body_override') ?: null,
            'is_active'        => $request->boolean('is_active', true),
            'last_edited_by'   => auth()->id(),
        ]);

        return redirect()
            ->route('admin.email-templates.index')
            ->with('success', '"' . $template->name . '" template updated successfully.');
    }

    /**
     * Toggle a template's active state (AJAX-friendly).
     */
    public function toggleEmailTemplate(EmailTemplate $template)
    {
        $template->update([
            'is_active'      => !$template->is_active,
            'last_edited_by' => auth()->id(),
        ]);

        $state = $template->is_active ? 'enabled' : 'disabled';

        return back()->with('success', '"' . $template->name . '" notification ' . $state . '.');
    }

    /**
     * Render a preview of the email template in the browser.
     * Uses a fake model so admins can see what the final email looks like.
     */
    public function previewEmailTemplate(EmailTemplate $template)
    {
        // Build a safe dummy user for rendering
        $fakeUser           = new User();
        $fakeUser->id       = 0;
        $fakeUser->name     = 'Jane Doe (Preview)';
        $fakeUser->email    = 'preview@example.com';
        $fakeUser->user_type = 'voter';

                // Referral / Sharing templates: render a lightweight browser preview card.
                if ($template->category === 'referral') {
                        $sampleBindings = [
                                '{{politician.name}}' => 'Jane Smith',
                                '{{referral_code}}'   => 'VOTER-PREVIEW',
                                '{{referral_link}}'   => url('/?ref=VOTER-PREVIEW&target=voter'),
                                '{{platform_name}}'   => config('app.name', 'U9itus'),
                        ];
                        $shareMessage = $template->body_override
                                ? str_replace(array_keys($sampleBindings), array_values($sampleBindings), $template->body_override)
                                : '(using built-in default)';

                        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Preview: {$template->name}</title>
<style>
    body{font-family:system-ui,sans-serif;background:#1e293b;color:#e2e8f0;margin:0;padding:2rem}
    .card{background:#0f172a;border:1px solid #334155;border-radius:12px;max-width:540px;margin:0 auto;padding:1.5rem}
    h1{font-size:1rem;color:#94a3b8;margin:0 0 1.25rem}
    .row{margin-bottom:1rem}
    .label{font-size:.7rem;text-transform:uppercase;letter-spacing:.08em;color:#64748b;margin-bottom:.3rem}
    .value{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:.75rem 1rem;font-size:.875rem;color:#e2e8f0;white-space:pre-wrap;word-break:break-word}
    .badge{display:inline-block;font-size:.7rem;padding:.2rem .6rem;border-radius:9999px;border:1px solid #d97706;color:#fbbf24;background:rgba(217,119,6,.1);margin-bottom:1.25rem}
    .empty{color:#64748b;font-style:italic}
</style>
</head>
<body>
<div class="card">
    <h1>Share Template Preview - {$template->name}</h1>
    <div class="badge">Referral / Sharing</div>
    <div class="row">
        <div class="label">Share Title / Email Subject</div>
        <div class="value">
HTML;
                        $html .= $template->subject_override
                                ? htmlspecialchars($template->subject_override)
                                : '<span class="empty">(using built-in default - set Subject Override to customise)</span>';
                        $html .= <<<HTML
        </div>
    </div>
    <div class="row">
        <div class="label">Share Message (with sample variables)</div>
        <div class="value">
HTML;
                        $html .= $template->body_override
                                ? htmlspecialchars($shareMessage)
                                : '<span class="empty">(using built-in default - set Share Message to customise)</span>';
                        $html .= <<<HTML
        </div>
    </div>
    <div class="row">
        <div class="label">Available Variables</div>
        <div class="value">
HTML;
                        foreach (($template->available_variables ?? []) as $var) {
                                $html .= '<code style="display:inline-block;margin:.15rem .25rem;background:#1e293b;border:1px solid #334155;border-radius:4px;padding:.15rem .4rem;font-size:.75rem;color:#34d399">' . htmlspecialchars($var) . '</code>';
                        }
                        $html .= <<<HTML
        </div>
    </div>
</div>
</body></html>
HTML;

                        return response($html)->header('Content-Type', 'text/html');
                }

        // If there is a body override, render it directly
        if ($template->hasBodyOverride()) {
            return response($template->body_override)->header('Content-Type', 'text/html');
        }

        // Otherwise render the corresponding Blade view directly
        $viewMap = [
            'kyc_approved'          => 'emails.kyc-approved',
            'kyc_rejected'          => 'emails.kyc-rejected',
            'campaign_approved'     => 'emails.campaign-approved',
            'campaign_rejected'     => 'emails.campaign-rejected',
            'campaign_completed'    => 'emails.campaign-completed',
            'campaign_reactivated'  => 'emails.campaign-reactivated',
            'credits_purchased'     => 'emails.credits-purchased',
            'credits_refunded'      => 'emails.credits-refunded',
            'low_balance_alert'     => 'emails.low-balance-alert',
            'payout_processed'      => 'emails.payout-processed',
            'welcome'               => 'emails.welcome',
            'account_unsuspended'   => 'emails.account-unsuspended',
            'admin_new_user'        => 'emails.admin-new-user',
            'admin_password_reset'  => 'emails.admin-password-reset',
            'admin_account_created' => 'emails.admin-account-created',
            'profile_verification'  => 'emails.profile-verification',
        ];

        $view = $viewMap[$template->key] ?? null;

        if (!$view || !\Illuminate\Support\Facades\View::exists($view)) {
            return response('<p>No preview available for this template.</p>');
        }

        // Shared fake data passed to all previews
        $sharedData = [
            'user'          => $fakeUser,
            'reason'        => 'Your document image was unclear. Please re-upload a high-resolution photo.',
            'credits'       => 500,
            'amount'        => 275.00,
            'refundedCredits' => 120.00,
            'newBalance'    => 275.00,
            'transactionId' => 'txn_preview_00001',
            'currentBalance'=> 12.50,
            'remainingViews'=> 20,
            'campaignTitle' => 'Re-elect Mayor Johnson 2026 (Preview)',
            'viewCount'     => 312,
            'payoutMethod'  => 'PayPal',
            'periodLabel'   => 'Feb 1 – Feb 22, 2026',
            'totalViews'    => 500,
            'totalSpent'    => 275.00,
        ];

        // Template-specific variable names (matching each Mail class's constructor)

        // Campaign templates expect a $campaign object (PoliticalCampaign), not flat strings.
        if (in_array($template->key, ['campaign_approved', 'campaign_rejected', 'campaign_completed', 'campaign_reactivated'], true)) {
            $fakePoliticianUser             = new \stdClass();
            $fakePoliticianUser->first_name = 'Jane';

            $fakePolitician             = new \stdClass();
            $fakePolitician->user       = $fakePoliticianUser;
            $fakePolitician->full_name  = 'Jane Doe';

            $fakeCampaign                   = new \stdClass();
            $fakeCampaign->id               = 0;
            $fakeCampaign->title            = 'Re-elect Mayor Johnson 2026 (Preview)';
            $fakeCampaign->governance_level = 'local';
            $fakeCampaign->target_state     = 'CA';
            $fakeCampaign->politician       = $fakePolitician;

            $sharedData['campaign'] = $fakeCampaign;
        }

        if ($template->key === 'admin_new_user') {
            $sharedData['newUser'] = $fakeUser;
        }

        if (in_array($template->key, ['admin_password_reset', 'admin_account_created'], true)) {
            $sharedData['admin'] = $fakeUser;
        }

        if ($template->key === 'admin_account_created') {
            $sharedData['isNew']    = true;
            $sharedData['tempPass'] = 'Pr3view#Pass!';
        }

        if ($template->key === 'profile_verification') {
            $sharedData['politicianName']    = 'Jane Doe (Preview)';
            $sharedData['verificationUrl']   = url('/preview/verify-profile/fake-token');
            $sharedData['expiryHours']       = 48;
        }

        return view($view, $sharedData);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Admin Profile
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Show the admin profile / account settings page.
     */
    public function profile()
    {
        $user = auth()->user();

        $adminTwoFactorEnforced = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        return view('standalone.admin.profile', compact('user', 'adminTwoFactorEnforced'));
    }

    /**
     * Show admin TOTP setup page.
     */
    public function twoFactorSetup(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $user = $request->user();
        $isEnabled = $user->hasAdminTwoFactorEnabled();
        $setupSecret = null;
        $otpAuthUrl = null;
        $otpQrSvg = null;
        $newRecoveryCodes = $request->session()->get('admin_2fa_new_recovery_codes', []);

        if (!$isEnabled) {
            $setupSecret = (string) $request->session()->get('admin_2fa_setup_secret');

            if ($setupSecret === '') {
                $setupSecret = $twoFactorService->generateSecret();
                $request->session()->put('admin_2fa_setup_secret', $setupSecret);
            }

            $otpAuthUrl = $twoFactorService->getOtpAuthUrl($user, $setupSecret);
            $otpQrSvg = $twoFactorService->renderOtpAuthQrSvg($otpAuthUrl);
        }

        return view('standalone.admin.security.2fa-setup', compact('isEnabled', 'setupSecret', 'otpAuthUrl', 'otpQrSvg', 'newRecoveryCodes'));
    }

    /**
     * Confirm and enable admin TOTP.
     */
    public function enableTwoFactor(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if ($user->hasAdminTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication is already enabled.']);
        }

        $secret = (string) $request->session()->get('admin_2fa_setup_secret', '');

        if ($secret === '') {
            return back()->withErrors(['code' => 'Setup secret expired. Reload the page and try again.']);
        }

        if (!$twoFactorService->verifyCode($secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid authenticator code for setup confirmation.']);
        }

        $recoveryCodes = $twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'admin_two_factor_secret' => $secret,
            'admin_two_factor_confirmed_at' => now(),
            'admin_two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        $request->session()->forget('admin_2fa_setup_secret');
        $request->session()->put('admin_2fa_verified_user_id', (int) $user->id);
        $request->session()->put('admin_2fa_verified_at', now()->toIso8601String());
        $request->session()->flash('admin_2fa_new_recovery_codes', $recoveryCodes);

        Log::info('Admin enabled two-factor authentication', [
            'admin_id' => $user->id,
        ]);

        AdminSecurityAuditLog::record(
            $user,
            'admin.2fa.enabled',
            ['recovery_code_count' => count($recoveryCodes)],
            $request
        );

        return back()->with('success', 'Two-factor authentication enabled successfully.');
    }

    /**
     * Disable admin TOTP after credential verification.
     */
    public function disableTwoFactor(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'digits:6'],
        ]);

        $isEnforced = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($isEnforced) {
            return back()->withErrors(['code' => 'Global admin 2FA policy is enabled. Disable policy before disabling your authenticator.']);
        }

        $user = $request->user();

        if (!$user->hasAdminTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication is not enabled for this account.']);
        }

        if (!$twoFactorService->verifyCode((string) $user->admin_two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid authenticator code.']);
        }

        $user->forceFill([
            'admin_two_factor_secret' => null,
            'admin_two_factor_confirmed_at' => null,
            'admin_two_factor_recovery_codes' => null,
        ])->save();

        $request->session()->forget(['admin_2fa_verified_user_id', 'admin_2fa_verified_at']);

        Log::info('Admin disabled two-factor authentication', [
            'admin_id' => $user->id,
        ]);

        AdminSecurityAuditLog::record($user, 'admin.2fa.disabled', [], $request);

        return back()->with('success', 'Two-factor authentication disabled successfully.');
    }

    /**
     * Rotate recovery codes after password + authenticator verification.
     */
    public function rotateRecoveryCodes(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if (!$user->hasAdminTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication must be enabled before rotating recovery codes.']);
        }

        $inputCode = (string) $request->input('code');
        $method = null;

        if (preg_match('/^\d{6}$/', $inputCode) === 1) {
            if (!$twoFactorService->verifyCode((string) $user->admin_two_factor_secret, $inputCode)) {
                return back()->withErrors(['code' => 'Invalid authenticator code.']);
            }

            $method = 'totp';
        } else {
            $existingCodes = (array) ($user->admin_two_factor_recovery_codes ?? []);
            $remainingCodes = $twoFactorService->consumeRecoveryCode($existingCodes, $inputCode);

            if ($remainingCodes === null) {
                return back()->withErrors(['code' => 'Invalid recovery code.']);
            }

            $method = 'recovery_code';
        }

        $newRecoveryCodes = $twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'admin_two_factor_recovery_codes' => $newRecoveryCodes,
        ])->save();

        $request->session()->flash('admin_2fa_new_recovery_codes', $newRecoveryCodes);

        AdminSecurityAuditLog::record(
            $user,
            'admin.2fa.recovery_codes.rotated',
            [
                'verified_by' => $method,
                'recovery_code_count' => count($newRecoveryCodes),
            ],
            $request
        );

        return back()->with('success', 'Recovery codes rotated successfully. Save your new codes now.');
    }

    /**
     * Update admin name, email, or password.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'current_password'      => ['nullable', 'current_password'],
            'password'              => ['nullable', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name  = $validated['last_name'];
        $user->email      = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
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
            'category' => 'nullable|string|in:pricing,fraud,video,referral,general',
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

    /**
     * Show California import logs and monitoring dashboard.
     *
     * Displays all scheduled import runs with status, counts, and error details.
     */
    public function imports()
    {
        $imports = \App\Models\ImportRunLog::query()
            ->where('command_name', 'politicians:import-unclaimed-ca')
            ->latest('started_at')
            ->paginate(20);

        $latestRun = \App\Models\ImportRunLog::query()
            ->where('command_name', 'politicians:import-unclaimed-ca')
            ->latest('started_at')
            ->first();

        return view('standalone.admin.imports.index', compact('imports', 'latestRun'));
    }

    /**
     * Trigger a one-off unverified politician profile seed from an official website.
     */
    public function seedUnverifiedPoliticianProfile(Request $request)
    {
        $validated = $request->validate([
            'website' => ['required', 'url'],
            'name' => ['required', 'string', 'max:255'],
            'office' => ['required', 'string', 'max:255'],
            'level' => ['nullable', 'string', 'max:100'],
            'state' => ['required', 'string', 'size:2'],
            'district' => ['nullable', 'string', 'max:120'],
            'party' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'photo_url' => ['nullable', 'url'],
            'source' => ['nullable', 'string', 'max:64'],
            'publish' => ['nullable', 'boolean'],
        ]);

        $arguments = [
            '--website' => (string) $validated['website'],
            '--name' => (string) $validated['name'],
            '--office' => (string) $validated['office'],
            '--level' => (string) ($validated['level'] ?? 'State'),
            '--state' => strtoupper((string) $validated['state']),
            '--district' => (string) ($validated['district'] ?? ''),
            '--party' => (string) ($validated['party'] ?? ''),
            '--city' => (string) ($validated['city'] ?? ''),
            '--bio' => (string) ($validated['bio'] ?? ''),
            '--photo-url' => (string) ($validated['photo_url'] ?? ''),
            '--source' => (string) ($validated['source'] ?? 'official_state_website'),
            '--publish' => ($request->boolean('publish', true) ? '1' : '0'),
        ];

        $exitCode = Artisan::call('politicians:create-unverified-profile', $arguments);
        $output = trim((string) Artisan::output());

        if ($exitCode !== 0) {
            return back()->withErrors([
                'unverified_profile' => $output !== ''
                    ? $output
                    : 'Unable to run one-off unverified profile import.',
            ])->withInput();
        }

        return back()->with('success', $output !== ''
            ? 'Unverified profile import completed. ' . $output
            : 'Unverified profile import completed.');
    }

    /**
     * OCR-assisted candidate import for scanned local election packages.
     *
     * Stores the upload then dispatches a queue job so the OCR + artisan import
     * run asynchronously — avoiding the Railway web-worker request timeout.
     */
    public function importCandidatesFromOcr(Request $request)
    {
        $validated = $request->validate([
            'source' => ['required', 'string', 'max:64'],
            'scan_upload' => ['required', 'file', 'mimes:pdf,png,jpg,jpeg,tif,tiff,bmp,webp,txt,json', 'max:20480'],
            'state' => ['nullable', 'string', 'size:2'],
            'political_office' => ['nullable', 'string', 'max:255'],
            'governance_level' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'party_affiliation' => ['nullable', 'string', 'max:120'],
            'election_date' => ['nullable', 'date'],
            'dry_run' => ['nullable', 'boolean'],
        ]);

        $upload = $request->file('scan_upload');
        $extension = strtolower((string) $upload?->getClientOriginalExtension());
        $safeName = 'candidate-ocr-' . now()->format('Ymd-His') . '-' . uniqid('', true) . '.' . $extension;
        $storedRelative = $upload?->storeAs('imports/uploads', $safeName, 'local');
        $storedPath = Storage::disk('local')->path((string) $storedRelative);

        $defaults = [
            'state' => isset($validated['state']) ? strtoupper((string) $validated['state']) : '',
            'political_office' => (string) ($validated['political_office'] ?? ''),
            'governance_level' => (string) ($validated['governance_level'] ?? ''),
            'district' => (string) ($validated['district'] ?? ''),
            'county' => (string) ($validated['county'] ?? ''),
            'city' => (string) ($validated['city'] ?? ''),
            'party_affiliation' => (string) ($validated['party_affiliation'] ?? ''),
            'election_date' => isset($validated['election_date']) ? (string) $validated['election_date'] : '',
        ];

        ProcessOcrCandidateImportJob::dispatch(
            storedPath: $storedPath,
            source: (string) $validated['source'],
            dryRun: $request->boolean('dry_run'),
            defaults: $defaults,
        );

        return back()->with(
            'success',
            'OCR import job queued. The scan is being processed in the background — '
            . 'check the Rails logs or candidate matches section in a few minutes.'
        );
    }
}
