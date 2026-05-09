<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CampaignType;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\PaymentModeFilterable;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCampaignRequest;
use App\Http\Requests\SaveCampaignDraftRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\PoliticianTopic;
use App\Models\ReferralVisit;
use App\Models\VoterWatchReport;
use App\Services\StripePaymentService;
use App\Services\PlatformSettingsService;
use App\Services\CampaignQandAService;
use App\Services\TransactionEngagementService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Services\CampaignBillingService;
use Throwable;

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
    use PaymentModeFilterable;

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

    private function isIosUserAgent(?string $userAgent): bool
    {
        $ua = $userAgent ?? '';
        return preg_match('/\b(iPhone|iPad|iPod)\b/i', $ua) === 1;
    }

    /**
     * Resolve video duration bounds from dynamic platform settings.
     *
     * @return array{0:int,1:int} [minSeconds, maxSeconds]
     */
    private function videoDurationBounds(): array
    {
        $configuredMin = (int) PlatformSettingsService::get(
            'min_video_duration',
            null,
            (int) config('u9itus.min_video_duration', 10)
        );
        $configuredMax = (int) PlatformSettingsService::get(
            'max_video_duration',
            null,
            (int) config('u9itus.max_video_duration', 180)
        );

        $min = max(1, $configuredMin);
        $max = max($min, $configuredMax);

        return [$min, $max];
    }

    /**
     * Load active campaign topics for form dropdowns without hard-failing the page.
     */
    private function safeActiveTopics()
    {
        try {
            return PoliticianTopic::active()->orderBy('sort_order')->get();
        } catch (Throwable $e) {
            Log::warning('Unable to load politician topics for campaign form', [
                'error' => $e->getMessage(),
            ]);

            return collect();
        }
    }

    /**
     * Store a campaign video on the configured disk and return its canonical URL.
     *
     * Persisting long temporary signed URLs can overflow the media_url column, so we
     * store the stable disk URL here and only generate temporary signed URLs when
     * rendering for playback.
     */
    private function storeCampaignVideoAndGetUrl(UploadedFile $video, PoliticalCampaign $campaign): ?string
    {
        $disk = (string) config('filesystems.default', 'local');
        $disks = (array) config('filesystems.disks', []);

        if (! array_key_exists($disk, $disks)) {
            Log::error('Campaign video upload failed: filesystem disk is not configured', [
                'campaign_id' => $campaign->id,
                'disk' => $disk,
            ]);

            return null;
        }

        try {
            $path = $video->store("campaigns/{$campaign->id}/video", $disk);

            if (! is_string($path) || $path === '') {
                Log::error('Campaign video upload failed: storage returned empty path', [
                    'campaign_id' => $campaign->id,
                    'disk' => $disk,
                ]);

                return null;
            }

            return Storage::disk($disk)->url($path);
        } catch (Throwable $e) {
            Log::error('Campaign video upload failed with exception', [
                'campaign_id' => $campaign->id,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Compute balance from filtered ledger rows while ignoring duplicate rows
     * generated for the same related transaction id.
     */
    private function computeModeAwareCreditBalance(int $politicianId, string $mode): float
    {
        $entries = $this->modeAwareCreditLedgerQuery($politicianId, $mode)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['id', 'transaction_type', 'amount', 'related_transaction_id']);

        if ($entries->isEmpty()) {
            $hasAnyLedgerRows = PoliticianCredit::where('politician_id', $politicianId)->exists();

            if ($hasAnyLedgerRows) {
                return 0.0;
            }

            return round((float) (Politician::whereKey($politicianId)->value('credit_balance') ?? 0.0), 2);
        }

        $seenRelated = [];
        $balance = 0.0;

        foreach ($entries as $entry) {
            $relatedId = $entry->related_transaction_id;
            $shouldDedupe = $relatedId !== null && in_array($entry->transaction_type, ['purchase', 'refund'], true);

            if ($shouldDedupe) {
                $dedupeKey = $entry->transaction_type . ':' . $relatedId;

                if (isset($seenRelated[$dedupeKey])) {
                    continue;
                }

                $seenRelated[$dedupeKey] = true;
            }

            $balance += (float) $entry->amount;
        }

        return round($balance, 2);
    }

    /**
     * Scope a politician credit ledger query to the active payment mode.
     *
     * Historical usage rows were stored without metadata.payment_mode.
     * To keep balances accurate, include those usage rows via campaign_id
     * when the campaign has transaction activity in the selected mode.
     */
    private function modeAwareCreditLedgerQuery(int $politicianId, string $mode)
    {
        $modeCampaignIds = $this->applyPaymentModeFilter(
            CampaignTransaction::query()
                ->select('campaign_id')
                ->where('politician_id', $politicianId)
                ->whereNotNull('campaign_id')
                ->distinct(),
            $mode
        );

        return PoliticianCredit::query()
            ->where('politician_id', $politicianId)
            ->where(function ($query) use ($mode, $modeCampaignIds) {
                $query->where('metadata->payment_mode', $mode)
                    ->orWhere(function ($usageQuery) use ($modeCampaignIds) {
                        $usageQuery->where('transaction_type', 'usage')
                            ->whereIn('campaign_id', $modeCampaignIds);
                    });
            });
    }

    /**
     * Campaign ids associated with the selected payment mode.
     *
     * We infer campaign mode from campaign transaction metadata so analytics
     * cards and campaign breakdowns do not blend live and test cohorts.
     *
     * @return array<int, int>
     */
    private function modeAwareCampaignIds(int $politicianId, string $mode): array
    {
        return $this->applyPaymentModeFilter(
            CampaignTransaction::query()
                ->select('campaign_id')
                ->where('politician_id', $politicianId)
                ->whereNotNull('campaign_id')
                ->distinct(),
            $mode
        )
            ->pluck('campaign_id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
            ->withCount([
                'voterWatchReports as open_voter_questions_count' => function ($query) {
                    $query->messages()->where('status', 'open');
                },
                'voterWatchReports as pending_public_questions_count' => function ($query) {
                    $query->messages()->where('public_visibility', 'pending');
                },
            ])
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        $activePaymentMode = $this->activePaymentMode();

        // Keep dashboard balance consistent with billing by mode-filtering the ledger.
        $creditBalance = $this->computeModeAwareCreditBalance($politician->id, $activePaymentMode);

        $stats = [
            'active_campaigns'  => $politician->campaigns()->where('status', 'active')->count(),
            'total_campaigns'   => $politician->campaigns()->count(),
            'total_views'       => $politician->total_views_received ?? 0,
            'total_spent'       => $politician->total_spent ?? 0.00,
            'credit_balance'    => $creditBalance,
            'open_voter_questions' => VoterWatchReport::query()
                ->messages()
                ->where('status', 'open')
                ->whereHas('campaign', function ($query) use ($politician) {
                    $query->where('politician_id', $politician->id);
                })
                ->count(),
            'pending_public_questions' => VoterWatchReport::query()
                ->messages()
                ->where('public_visibility', 'pending')
                ->whereHas('campaign', function ($query) use ($politician) {
                    $query->where('politician_id', $politician->id);
                })
                ->count(),
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

        $validCampaignStatuses = array_column(CampaignStatus::cases(), 'value');
        $validCampaignTypes = array_column(CampaignType::cases(), 'value');
        $validApprovalStatuses = array_column(ApprovalStatus::cases(), 'value');
        $validPaymentStatuses = array_column(PaymentStatus::cases(), 'value');

        // Guard against legacy/invalid enum values in staging data that can
        // throw ValueError during Eloquent enum casting.
        $query = $politician->campaigns()
            ->whereIn('status', $validCampaignStatuses)
            ->where(function ($q) use ($validCampaignTypes): void {
                $q->whereNull('campaign_type')
                    ->orWhereIn('campaign_type', $validCampaignTypes);
            })
            ->where(function ($q) use ($validApprovalStatuses): void {
                $q->whereNull('approval_status')
                    ->orWhereIn('approval_status', $validApprovalStatuses);
            })
            ->where(function ($q) use ($validPaymentStatuses): void {
                $q->whereNull('payment_status')
                    ->orWhereIn('payment_status', $validPaymentStatuses);
            })
            ->orderByDesc('created_at');
        
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

        $revenuePerView = (float) PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00));
        $creditBalance  = $this->computeModeAwareCreditBalance(
            $politician->id,
            $this->activePaymentMode()
        );
        $states = config('u9itus.us_states', []);
        $topics = $this->safeActiveTopics();
        $governanceLevels = config('u9itus.governance_levels', [
            'Federal' => 'Federal', 'State' => 'State', 'County' => 'County',
            'City' => 'City', 'School Board' => 'School Board',
        ]);

        return view('standalone.politician.campaigns.create', compact(
            'politician', 'revenuePerView', 'creditBalance', 'states', 'governanceLevels', 'topics'
        ));
    }

    /** Store a new campaign. */
    public function storeCampaign(CreateCampaignRequest $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $data = $request->validated();
        $uploadedVideo = $request->file('video');
        unset($data['video']);

        if ($uploadedVideo) {
            // File uploads take precedence over URL input to avoid mixed-source state.
            unset($data['media_url']);
            $data['media_type'] = 'direct_file';
        } elseif (!empty($data['media_url'])) {
            $data['media_type'] = $this->inferMediaTypeFromUrl(
                $data['media_url'],
                $data['media_type'] ?? 'direct_file'
            );
        }

        $data['politician_id'] = $politician->id;
        $data['status']        = 'draft';
        $data['revenue_per_view'] = (float) PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00));
        $data['voter_payout_per_view'] = (float) PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
        // Always recompute total_budget from views × rate (never trust form input)
        $data['total_budget'] = round((float)($data['total_views_requested'] ?? 0) * $data['revenue_per_view'], 2);

        $campaign = PoliticalCampaign::create($data);

        // Handle Sprint 3 - Topic syncing and Q&A parsing
        $qaService = app(CampaignQandAService::class);
        
        if (!empty($data['topic_ids'])) {
            $qaService->syncTopics($campaign, $data['topic_ids']);
        }
        
        if (!empty($data['qa_items'])) {
            $parsedQA = $qaService->parseQAItems($data['qa_items']);
            $campaign->update(['qa_items' => $parsedQA]);
        }
        
        if (!empty($data['engagement_survey'])) {
            $parsedSurvey = $qaService->parseEngagementSurvey($data['engagement_survey']);
            $campaign->update(['engagement_survey' => $parsedSurvey]);
        }

        if ($uploadedVideo) {
            $mediaUrl = $this->storeCampaignVideoAndGetUrl($uploadedVideo, $campaign);

            if (! $mediaUrl) {
                return redirect()
                    ->route('politician.campaigns.show', $campaign)
                    ->withErrors(['video' => 'Campaign created, but video upload failed. Please check storage settings and try again.']);
            }

            $campaign->update([
                'media_url' => $mediaUrl,
                'media_type' => 'direct_file',
            ]);
        }

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
        $data['revenue_per_view'] = (float) PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00));
        $data['voter_payout_per_view'] = (float) PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
        
        // Ensure we have at least a title for the draft
        if (!isset($data['title']) || empty($data['title'])) {
            $data['title'] = 'Draft Campaign - ' . now()->format('M d, Y g:i A');
        }

        $campaign = PoliticalCampaign::create($data);

        // Handle Sprint 3 - Topic syncing and Q&A parsing in drafts
        $qaService = app(CampaignQandAService::class);
        
        if (!empty($data['topic_ids'])) {
            $qaService->syncTopics($campaign, $data['topic_ids']);
        }
        
        if (!empty($data['qa_items'])) {
            $parsedQA = $qaService->parseQAItems($data['qa_items']);
            $campaign->update(['qa_items' => $parsedQA]);
        }
        
        if (!empty($data['engagement_survey'])) {
            $parsedSurvey = $qaService->parseEngagementSurvey($data['engagement_survey']);
            $campaign->update(['engagement_survey' => $parsedSurvey]);
        }

        return redirect()
            ->route('politician.campaigns.edit', $campaign)
            ->with('success', 'Draft saved! You can complete it anytime.');
    }

    /** Show campaign detail. */
    public function showCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        $rawCampaignType = (string) ($campaign->getRawOriginal('campaign_type') ?? '');
        $rawApprovalStatus = (string) ($campaign->getRawOriginal('approval_status') ?? '');

        $campaignStatus = CampaignStatus::tryFrom($rawStatus)?->value
            ?? ($rawStatus !== '' ? $rawStatus : CampaignStatus::Draft->value);
        $campaignType = CampaignType::tryFrom($rawCampaignType)?->value
            ?? ($rawCampaignType !== '' ? $rawCampaignType : CampaignType::Video->value);
        $campaignApprovalStatus = ApprovalStatus::tryFrom($rawApprovalStatus)?->value
            ?? ($rawApprovalStatus !== '' ? $rawApprovalStatus : ApprovalStatus::Pending->value);

        if (CampaignStatus::tryFrom($rawStatus) === null && $rawStatus !== '') {
            Log::warning('Campaign has non-standard status value', [
                'campaign_id' => $campaign->id,
                'status' => $rawStatus,
            ]);
        }

        if (CampaignType::tryFrom($rawCampaignType) === null && $rawCampaignType !== '') {
            Log::warning('Campaign has non-standard campaign_type value', [
                'campaign_id' => $campaign->id,
                'campaign_type' => $rawCampaignType,
            ]);
        }

        if (ApprovalStatus::tryFrom($rawApprovalStatus) === null && $rawApprovalStatus !== '') {
            Log::warning('Campaign has non-standard approval_status value', [
                'campaign_id' => $campaign->id,
                'approval_status' => $rawApprovalStatus,
            ]);
        }

        $completedViews = $campaign->views_completed ?? 0;
        $budgetUsed     = $campaign->amount_spent ?? 0;
        $budgetLeft     = ($campaign->total_budget ?? 0) - $budgetUsed;

        // Phase 14 — Repeat Viewing stats
        $uniqueVoters = $campaign->viewSessions()
            ->where('status', 'completed')
            ->distinct('voter_id')
            ->count('voter_id');

        $recentViewSessions = $campaign->viewSessions()
            ->select(['status', 'watch_time_seconds', 'completion_percentage', 'created_at'])
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(function ($session) {
                $rawSessionStatus = (string) ($session->getRawOriginal('status') ?? '');

                return [
                    'status' => $rawSessionStatus !== '' ? $rawSessionStatus : 'assigned',
                    'watch_time_seconds' => (int) ($session->watch_time_seconds ?? 0),
                    'completion_percentage' => (float) ($session->completion_percentage ?? 0),
                    'created_at' => $session->created_at,
                ];
            });

        $repeatViews    = max(0, $completedViews - $uniqueVoters);

        $creditBalance  = $this->computeModeAwareCreditBalance(
            $politician->id,
            $this->activePaymentMode()
        );

        // Refresh S3 media URLs to signed URLs (handles both new uploads and existing campaigns)
        // If campaign has an S3 media URL, regenerate a fresh signed URL valid for 7 days
        if ($campaign->media_url && strpos($campaign->media_url, 's3') !== false) {
            try {
                // Extract the S3 object path from the URL
                // URLs typically look like: https://bucket.s3.region.amazonaws.com/campaigns/438/video/file.mp4
                $urlParts = parse_url($campaign->media_url);
                $path = ltrim($urlParts['path'] ?? '', '/');
                
                // Remove bucket prefix if it's in the path (some URL formats include it)
                $bucketName = config('filesystems.disks.s3.bucket', '');
                if (stripos($path, $bucketName) === 0) {
                    $path = substr($path, strlen($bucketName) + 1);
                }

                if (!empty($path)) {
                    $campaign->media_url = Storage::disk('s3')->temporaryUrl($path, now()->addDays(7));
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to refresh S3 media URL for campaign display', [
                    'campaign_id' => $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return view('standalone.politician.campaigns.show', compact(
            'campaign', 'politician', 'completedViews', 'budgetUsed', 'budgetLeft',
            'uniqueVoters', 'repeatViews', 'creditBalance', 'campaignStatus',
            'campaignType', 'campaignApprovalStatus', 'recentViewSessions'
        ));
    }

    /** Show campaign edit form. */
    public function editCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        $rawApprovalStatus = (string) ($campaign->getRawOriginal('approval_status') ?? '');
        $canEditApprovedActive = $rawStatus === CampaignStatus::Active->value
            && $rawApprovalStatus === ApprovalStatus::Approved->value;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && (in_array($rawStatus, ['draft', 'paused', 'scheduled', 'cancelled'], true) || $canEditApprovedActive),
            403
        );

        $states = config('u9itus.us_states', []);
        $topics = $this->safeActiveTopics();
        $campaignTopicIds = [];
        try {
            $campaignTopicIds = $campaign->topics()->pluck('id')->toArray();
        } catch (Throwable $e) {
            Log::warning('Unable to load campaign topics for edit form', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);
        }
        $governanceLevels = config('u9itus.governance_levels', [
            'Federal' => 'Federal', 'State' => 'State', 'County' => 'County',
            'City' => 'City', 'School Board' => 'School Board',
        ]);

        return view('standalone.politician.campaigns.edit', compact(
            'campaign', 'politician', 'states', 'governanceLevels', 'topics', 'campaignTopicIds'
        ));
    }

    /** Update a campaign. */
    public function updateCampaign(UpdateCampaignRequest $request, PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? '');
        $rawApprovalStatus = (string) ($campaign->getRawOriginal('approval_status') ?? '');
        $canEditApprovedActive = $rawStatus === CampaignStatus::Active->value
            && $rawApprovalStatus === ApprovalStatus::Approved->value;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && (in_array($rawStatus, ['draft', 'paused', 'scheduled', 'cancelled'], true) || $canEditApprovedActive),
            403
        );

        $validated = $request->validated();
        $uploadedVideo = $request->file('video');
        unset($validated['video']);

        if ($uploadedVideo) {
            // File uploads take precedence over URL input to avoid mixed-source state.
            unset($validated['media_url']);
            $validated['media_type'] = 'direct_file';
        } elseif (!empty($validated['media_url'])) {
            $validated['media_type'] = $this->inferMediaTypeFromUrl(
                $validated['media_url'],
                $validated['media_type'] ?? 'direct_file'
            );
        }

        $revenuePerView = (float) PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00));
        // Always recompute total_budget from views × rate (never trust form input)
        $validated['total_budget'] = round(
            (float)($validated['total_views_requested'] ?? $campaign->total_views_requested)
            * $revenuePerView,
            2
        );

        // Handle Sprint 3 - Topic syncing and Q&A parsing
        $qaService = app(CampaignQandAService::class);
        
        if (isset($validated['topic_ids'])) {
            $qaService->syncTopics($campaign, $validated['topic_ids']);
            unset($validated['topic_ids']); // Remove from update array as topics are synced separately
        }
        
        if (!empty($validated['qa_items'])) {
            $parsedQA = $qaService->parseQAItems($validated['qa_items']);
            $validated['qa_items'] = $parsedQA;
        }
        
        if (!empty($validated['engagement_survey'])) {
            $parsedSurvey = $qaService->parseEngagementSurvey($validated['engagement_survey']);
            $validated['engagement_survey'] = $parsedSurvey;
        }

        // Allow approved active campaigns to be scheduled immediately after approval.
        if (($campaign->approval_status?->value ?? $campaign->getRawOriginal('approval_status')) === ApprovalStatus::Approved->value) {
            $hasScheduledStartInput = array_key_exists('scheduled_start_at', $validated);

            if ($hasScheduledStartInput && !empty($validated['scheduled_start_at'])) {
                $scheduledStartAt = \Illuminate\Support\Carbon::parse((string) $validated['scheduled_start_at']);

                if ($scheduledStartAt->isFuture()) {
                    $validated['status'] = CampaignStatus::Scheduled->value;
                } elseif (($campaign->status?->value ?? $campaign->getRawOriginal('status')) === CampaignStatus::Scheduled->value) {
                    $validated['status'] = CampaignStatus::Active->value;
                }
            } elseif ($hasScheduledStartInput && (($campaign->status?->value ?? $campaign->getRawOriginal('status')) === CampaignStatus::Scheduled->value)) {
                // Clearing the schedule should resume active delivery for approved campaigns.
                $validated['status'] = CampaignStatus::Active->value;
            }
        }

        $campaign->update($validated);

        if ($uploadedVideo) {
            $mediaUrl = $this->storeCampaignVideoAndGetUrl($uploadedVideo, $campaign);

            if (! $mediaUrl) {
                return redirect()
                    ->route('politician.campaigns.show', $campaign)
                    ->withErrors(['video' => 'Campaign updated, but video upload failed. Please check storage settings and try again.']);
            }

            $campaign->update([
                'media_url' => $mediaUrl,
                'media_type' => 'direct_file',
            ]);
        }

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
            in_array(($campaign->status?->value ?? $campaign->status), ['draft', 'cancelled'], true),
            422,
            'Only draft or cancelled campaigns can be submitted for review.'
        );

        abort_unless(
            $campaign->media_url || $campaign->live_feed_url,
            422,
            'Please upload a video or set a live stream URL before submitting.'
        );

        if (! $campaign->governance_level) {
            return back()->withErrors([
                'governance_level' => 'Please select a governance level before submitting this campaign for review.',
            ]);
        }

        // Credit gate: politician must hold enough balance to cover the full campaign budget.
        $balance = $this->computeModeAwareCreditBalance(
            $politician->id,
            $this->activePaymentMode()
        );
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
            $politician && (int) $campaign->politician_id === (int) $politician->id,
            403
        );

        $rawStatus = (string) ($campaign->getRawOriginal('status') ?? $campaign->status?->value ?? $campaign->status);
        if (! in_array($rawStatus, ['draft', 'paused'], true)) {
            $statusLabel = ucfirst(str_replace('_', ' ', $rawStatus ?: 'unknown'));
            $message = "Video uploads are only allowed when the campaign is Draft or Paused. Current status: {$statusLabel}.";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->withErrors(['video' => $message]);
        }

        $maxMb  = config('u9itus.max_video_size_mb', 1024);
        [$minSec, $maxSec] = $this->videoDurationBounds();
        $videoMimeTypes = ['video/mp4', 'video/webm'];

        if ($this->isIosUserAgent($request->userAgent())) {
            $videoMimeTypes[] = 'video/quicktime';
        }

        $request->validate([
            'video' => [
                'required',
                'file',
                'mimetypes:' . implode(',', $videoMimeTypes),
                'max:' . ($maxMb * 1024),
            ],
        ]);

        $file = $request->file('video');

        if ($file && ! $this->isIosUserAgent($request->userAgent()) && $file->getMimeType() === 'video/quicktime') {
            return back()->withErrors([
                'video' => 'MOV uploads are only allowed from iOS devices. Use MP4 or WebM on non-iOS devices.',
            ]);
        }

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

        $url = $this->storeCampaignVideoAndGetUrl($file, $campaign);

        if (! $url) {
            return back()->withErrors([
                'video' => 'Video upload failed due to a storage configuration issue. Please contact support or try again later.',
            ]);
        }

        $campaign->update(array_filter([
            'media_url'      => $url,
            'media_type'     => 'direct_file',
            'media_duration' => $campaign->media_duration,
        ], fn ($v) => $v !== null));

        return back()->with('success', 'Video uploaded successfully.');
    }

    /**
     * Get a presigned URL for direct browser-to-S3 upload.
     * 
     * Allows large file uploads to bypass the web server and upload directly to S3,
     * then trigger background transcoding on completion.
     */
    public function getS3UploadUrl(Request $request, PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused']),
            403
        );

        $request->validate([
            'filename' => 'required|string|max:255',
            'content_type' => 'required|in:video/mp4,video/quicktime,video/webm',
        ]);

        $filename = $request->input('filename');
        $contentType = $request->input('content_type');

        // Reject MOV on non-iOS
        if ($contentType === 'video/quicktime' && !$this->isIosUserAgent($request->userAgent())) {
            return response()->json([
                'error' => 'MOV uploads are only allowed from iOS devices.',
            ], 422);
        }

        try {
            $s3Path = "campaigns/{$campaign->id}/uploads/" . time() . '-' . $filename;
            
            // Generate presigned URL valid for 1 hour
            $s3Client = \Aws\sdk::createClient('s3');
            $cmd = $s3Client->getCommand('PutObject', [
                'Bucket' => config('filesystems.disks.s3.bucket'),
                'Key' => $s3Path,
                'ContentType' => $contentType,
            ]);

            $request = $s3Client->createPresignedRequest($cmd, '+20 minutes');
            $presignedUrl = (string)$request->getUri();

            return response()->json([
                'presigned_url' => $presignedUrl,
                's3_path' => $s3Path,
                'expires_in' => 1200, // 20 minutes
            ]);
        } catch (\Exception $e) {
            logger()->error('Failed to generate S3 presigned URL', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to generate upload URL. Please try again.',
            ], 500);
        }
    }

    /**
     * Process a video that was uploaded directly to S3.
     * 
     * Called after the browser confirms successful S3 upload.
     * Validates file and queues transcoding job.
     */
    public function processS3UploadedVideo(Request $request, PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused']),
            403
        );

        $request->validate([
            's3_path' => 'required|string',
            'filename' => 'required|string|max:255',
            'file_size' => 'required|integer|min:1|max:' . ((int) config('u9itus.max_video_size_mb', 1024) * 1024 * 1024),
        ]);

        $s3Path = $request->input('s3_path');
        $maxMb = config('u9itus.max_video_size_mb', 1024);

        try {
            // Verify file exists in S3
            if (!Storage::disk('s3')->exists($s3Path)) {
                return back()->withErrors(['video' => 'Uploaded file not found in storage. Please try uploading again.']);
            }

            // Get video duration if FFprobe is available
            $duration = null;
            try {
                $ffprobe = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');
                if ($ffprobe) {
                    $s3Url = Storage::disk('s3')->url($s3Path);
                    $duration = (float) shell_exec(
                        escapeshellcmd($ffprobe)
                        . ' -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 '
                        . escapeshellarg($s3Url)
                        . ' 2>/dev/null'
                    );

                    if ($duration > 0) {
                        [$minSec, $maxSec] = $this->videoDurationBounds();

                        if ($duration < $minSec) {
                            Storage::disk('s3')->delete($s3Path);
                            return back()->withErrors(['video' => "Video is too short ({$duration}s). Minimum is {$minSec} seconds."]);
                        }
                        if ($duration > $maxSec) {
                            $rounded = round($duration, 1);
                            Storage::disk('s3')->delete($s3Path);
                            return back()->withErrors(['video' => "Video is too long ({$rounded}s). Maximum is {$maxSec} seconds."]);
                        }
                    }
                }
            } catch (\Exception $e) {
                logger()->warning('Could not extract duration from S3 video', ['error' => $e->getMessage()]);
            }

            // Generate destination path for transcoded video
            $transcodingService = app(\App\Services\VideoTranscodingService::class);
            $destinationPath = $transcodingService->generateTranscodedFilename(
                (string) $campaign->id,
                $request->input('filename')
            );

            // Delete old video if present
            if ($campaign->media_url) {
                try {
                    $oldPath = parse_url($campaign->media_url, PHP_URL_PATH);
                    if ($oldPath) {
                        Storage::disk('s3')->delete(ltrim($oldPath, '/'));
                    }
                } catch (\Exception $e) {
                    logger()->warning('Could not delete old video from S3', ['error' => $e->getMessage()]);
                }
            }

            // Update campaign with temporary status indicating processing
            $campaign->update([
                'media_url'      => Storage::disk('s3')->url($s3Path), // Temporary reference to uploaded file
                'media_type'     => 'direct_file',
                'media_duration' => $duration ? (int) round($duration) : null,
            ]);

            // Queue transcoding job for background processing
            \App\Jobs\TranscodeS3VideoJob::dispatch(
                $campaign,
                $s3Path,
                $destinationPath
            );

            return back()->with('success', 'Video uploaded! Your file is now being processed. This may take a few minutes for large files.');

        } catch (\Exception $e) {
            logger()->error('Failed to process S3 uploaded video', [
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['video' => 'Error processing your video. Please try again.']);
        }
    }

    /** Overall analytics for this politician (all campaigns). */
    public function analytics()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $activePaymentMode = $this->activePaymentMode();
        $modeCampaignIds = $this->modeAwareCampaignIds($politician->id, $activePaymentMode);
        $hasCampaignTransactions = CampaignTransaction::query()
            ->where('politician_id', $politician->id)
            ->whereNotNull('campaign_id')
            ->exists();

        $campaignsQuery = $politician->campaigns();

        if ($hasCampaignTransactions) {
            $campaignsQuery->whereIn('id', !empty($modeCampaignIds) ? $modeCampaignIds : [0]);
        }

        $campaigns = $campaignsQuery
            ->withCount(['viewSessions as total_sessions'])
            ->orderByDesc('created_at')
            ->get();

        $totalViews    = $campaigns->sum('views_completed');
        $activeCampaigns = $campaigns->filter(function (PoliticalCampaign $campaign): bool {
            return ($campaign->status?->value ?? (string) $campaign->status) === CampaignStatus::Active->value;
        })->count();

        $creditLedgerEntries = $this->modeAwareCreditLedgerQuery($politician->id, $activePaymentMode)
            ->get(['amount', 'transaction_type']);

        // Keep "Total Spent" mode-aware so it reconciles with purchased/available cards.
        $totalSpent = round((float) $creditLedgerEntries
            ->where('transaction_type', 'usage')
            ->sum(fn ($entry) => abs((float) ($entry->amount ?? 0))), 2);

        // Apply payment mode filter to purchase transactions (same as billing page)
        $transactionsWithFeeSummary = $this->applyPaymentModeFilter(
            CampaignTransaction::where('politician_id', $politician->id)
                ->where('transaction_type', 'charge')
                ->where('status', 'succeeded')
                ->whereNull('campaign_id'),
            $activePaymentMode
        )
            ->with(['credits' => function ($query) {
                $query->where('transaction_type', 'purchase');
            }])
            ->get()
            ->filter(function (CampaignTransaction $tx): bool {
                $purchaseCredits = $tx->credits;

                // Exclude reconciled ghost charges that never credited the ledger.
                if ($purchaseCredits->isNotEmpty()) {
                    return (float) $purchaseCredits->sum(fn ($entry) => (float) $entry->amount) > 0;
                }

                // Keep legacy rows that predate related transaction linkage.
                return true;
            })
            ->map(function (CampaignTransaction $tx): array {
                $metadata = is_array($tx->metadata) ? $tx->metadata : [];

                return [
                    'credits' => (float) ($metadata['credits_amount'] ?? 0),
                    'fee' => (float) ($metadata['stripe_fee'] ?? 0),
                    'gross' => (float) $tx->amount,
                ];
            });

        // Calculate totalBudget from actual available credits (purchases - debits)
        $totalBudget = $this->computeModeAwareCreditBalance($politician->id, $activePaymentMode);

        $voterQuestionsQuery = VoterWatchReport::query()
            ->where('type', 'message')
            ->whereHas('campaign', function ($query) use ($politician) {
                $query->where('politician_id', $politician->id);
            });

        $openVoterQuestionsCount = (clone $voterQuestionsQuery)
            ->where('status', 'open')
            ->count();

        $pendingPublicQuestionsCount = (clone $voterQuestionsQuery)
            ->where('public_visibility', 'pending')
            ->count();

        $recentVoterQuestions = (clone $voterQuestionsQuery)
            ->with([
                'campaign:id,title',
                'voter:id,full_name,email',
            ])
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('standalone.politician.analytics', compact(
            'politician', 'campaigns',
            'totalViews', 'totalSpent', 'totalBudget', 'activeCampaigns', 'transactionsWithFeeSummary',
            'openVoterQuestionsCount', 'pendingPublicQuestionsCount', 'recentVoterQuestions'
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

        $voterQuestionsBaseQuery = VoterWatchReport::query()
            ->where('campaign_id', $campaign->id)
            ->where('type', 'message');

        $openVoterQuestions = (clone $voterQuestionsBaseQuery)
            ->where('status', 'open')
            ->count();

        $voterQuestionCounts = (clone $voterQuestionsBaseQuery)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $pendingPublicQuestions = (clone $voterQuestionsBaseQuery)
            ->where('public_visibility', 'pending')
            ->count();

        $voterQuestions = (clone $voterQuestionsBaseQuery)
            ->with('voter:id,full_name,email')
            ->latest('created_at')
            ->paginate(10, ['*'], 'questions_page');

        return view('standalone.politician.analytics.campaign', compact(
            'campaign', 'politician', 'sessions', 'byStatus',
            'completedViews', 'budgetUsed', 'budgetLeft',
            'voterQuestions', 'voterQuestionCounts', 'openVoterQuestions', 'pendingPublicQuestions'
        ));
    }

    public function campaignQuestions(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $questionBaseQuery = VoterWatchReport::query()
            ->where('campaign_id', $campaign->id)
            ->where('type', 'message');

        $questionCounts = [
            'open' => (clone $questionBaseQuery)->where('status', 'open')->count(),
            'pending_public' => (clone $questionBaseQuery)->where('public_visibility', 'pending')->count(),
            'replied' => (clone $questionBaseQuery)->whereNotNull('campaign_reply')->count(),
            'total' => (clone $questionBaseQuery)->count(),
        ];

        $voterQuestions = (clone $questionBaseQuery)
            ->with('voter:id,full_name,email')
            ->latest('created_at')
            ->paginate(15);

        return view('standalone.politician.campaigns.questions', compact(
            'campaign',
            'politician',
            'questionCounts',
            'voterQuestions',
        ));
    }

    public function replyToQuestion(Request $request, PoliticalCampaign $campaign, VoterWatchReport $report)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);
        abort_unless((int) $report->campaign_id === (int) $campaign->id, 403);
        abort_unless($report->type === 'message', 422, 'Only voter questions can be replied to.');

        $validated = $request->validate([
            'campaign_reply' => ['required', 'string', 'max:2000'],
        ]);

        $report->campaign_reply = $validated['campaign_reply'];
        $report->campaign_replied_by = Auth::id();
        $report->campaign_replied_at = now();
        $report->status = 'resolved';
        $report->save();

        return back()->with('success', 'Reply posted to voter question.');
    }

    /** Create a Stripe SetupIntent so the politician can securely save a card. */
    public function createSetupIntent(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $stripe = app(StripePaymentService::class);
        try {
            $customerId = $stripe->ensureCustomer($politician);
            if (! $customerId) {
                return response()->json(['error' => 'Payment service unavailable.'], 500);
            }

            $setupIntent = $stripe->createSetupIntent($customerId);

            return response()->json([
                'client_secret' => $setupIntent->client_secret,
                'publishable_key' => config('services.stripe.public'),
            ]);
        } catch (\Exception $e) {
            Log::error('createSetupIntent failed: ' . $e->getMessage());
            return response()->json(['error' => 'Could not initialize card setup.'], 500);
        }
    }

    /** Save a Stripe PaymentMethod after a successful SetupIntent confirmation. */
    public function storePaymentMethod(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'payment_method_id' => ['required', 'string', 'regex:/^pm_/'],
        ]);

        $stripe = app(StripePaymentService::class);

        try {
            $paymentMethod = $stripe->retrievePaymentMethod($validated['payment_method_id']);
            $customerId = $stripe->ensureCustomer($politician);

            if ((string) ($paymentMethod->customer ?? '') !== (string) $customerId) {
                return response()->json(['error' => 'Invalid payment method.'], 422);
            }

            $existing = \App\Models\PoliticianPaymentMethod::where('politician_id', $politician->id)
                ->where('stripe_payment_method_id', $paymentMethod->id)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Card already saved.',
                    'id' => $existing->id,
                ]);
            }

            $card = $paymentMethod->card ?? null;
            $brand = $card ? ucfirst((string) ($card->brand ?? 'card')) : 'Card';
            $last4 = $card ? (string) ($card->last4 ?? '????') : '????';
            $exp = $card ? ((string) ($card->exp_month ?? '')) . '/' . ((string) ($card->exp_year ?? '')) : '';
            $label = "{$brand} •••• {$last4}" . ($exp !== '/' ? " expires {$exp}" : '');

            $isDefault = ! \App\Models\PoliticianPaymentMethod::where('politician_id', $politician->id)->exists();

            $saved = \App\Models\PoliticianPaymentMethod::create([
                'politician_id' => $politician->id,
                'stripe_customer_id' => $customerId,
                'stripe_payment_method_id' => $paymentMethod->id,
                'label' => $label,
                'is_default' => $isDefault,
            ]);

            Log::info('Saved payment method for politician', [
                'politician_id' => $politician->id,
                'pm_id' => $paymentMethod->id,
                'label' => $label,
            ]);

            return response()->json([
                'message' => 'Card saved.',
                'id' => $saved->id,
                'label' => $label,
                'is_default' => $saved->is_default,
            ]);
        } catch (\Exception $e) {
            Log::error('storePaymentMethod failed: ' . $e->getMessage());
            return response()->json(['error' => 'Could not save payment method.'], 500);
        }
    }

    /** Remove a saved Stripe PaymentMethod from Stripe and from local storage. */
    public function deletePaymentMethod(\App\Models\PoliticianPaymentMethod $paymentMethod)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $politician->id === (int) $paymentMethod->politician_id, 403);

        $stripe = app(StripePaymentService::class);

        try {
            if ($paymentMethod->stripe_payment_method_id) {
                $stripe->detachPaymentMethod($paymentMethod->stripe_payment_method_id);
            }
        } catch (\Exception $e) {
            Log::warning('Stripe detach failed (continuing delete): ' . $e->getMessage());
        }

        $wasDefault = $paymentMethod->is_default;
        $politicianId = $paymentMethod->politician_id;

        $paymentMethod->delete();

        if ($wasDefault) {
            $next = \App\Models\PoliticianPaymentMethod::where('politician_id', $politicianId)->first();
            $next?->update(['is_default' => true]);
        }

        return response()->json(['message' => 'Card removed.']);
    }

    /** Billing overview: credit balance + transaction history. */
    public function billing()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $activePaymentMode = $this->activePaymentMode();
        $creditBalance = $this->computeModeAwareCreditBalance($politician->id, $activePaymentMode);

        $credits = $this->modeAwareCreditLedgerQuery($politician->id, $activePaymentMode)
            ->orderByDesc('created_at')
            ->paginate(15);

        $transactions = $this->applyPaymentModeFilter(
            CampaignTransaction::where('politician_id', $politician->id),
            $activePaymentMode
        )->orderByDesc('created_at')->paginate(15);

        $savedPaymentMethods = \App\Models\PoliticianPaymentMethod::where('politician_id', $politician->id)
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        return view('standalone.politician.billing', compact(
            'politician', 'creditBalance', 'credits', 'transactions', 'activePaymentMode', 'savedPaymentMethods'
        ));
    }

    /**
     * Add funds via Stripe. Creates a PaymentIntent and returns the client secret.
     * Accepts an optional saved payment method to reuse a stored card.
     */
    public function addFunds(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:10000'],
            'saved_payment_method_id' => ['nullable', 'integer'],
        ]);

        /** @var \App\Services\CampaignBillingService $billing */
        $billing = app(\App\Services\CampaignBillingService::class);
        $options = ['description' => 'Credit top-up for politician #' . $politician->id];

        if (! empty($validated['saved_payment_method_id'])) {
            $savedPaymentMethod = \App\Models\PoliticianPaymentMethod::where('id', (int) $validated['saved_payment_method_id'])
                ->where('politician_id', $politician->id)
                ->first();

            if ($savedPaymentMethod?->stripe_payment_method_id) {
                $options['payment_method_id'] = $savedPaymentMethod->stripe_payment_method_id;
            }
        }

        try {
            $intentData = $billing->createPurchaseIntent($politician, (float) $validated['amount'], $options);
        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => $e->getMessage()], 500);
            }

            return back()->withErrors(['amount' => 'Payment service unavailable: ' . $e->getMessage()]);
        }

        $response = [
            'client_secret' => $intentData['client_secret'],
            'payment_intent' => $intentData['payment_intent_id'],
            'amount' => $intentData['gross_amount'],
            'credits_amount' => $intentData['credits_amount'],
            'stripe_fee' => $intentData['stripe_fee'],
            'stripe_fee_percent' => $intentData['stripe_fee_percent'],
            'publishable_key' => config('services.stripe.public'),
            'return_url' => route('politician.billing.confirm'),
        ];

        if (! empty($options['payment_method_id'])) {
            $response['stripe_payment_method_id'] = $options['payment_method_id'];
        }

        return response()->json($response);
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
     * Return invoice-level engagement analytics for a single paid charge.
     */
    public function invoiceDetails(CampaignTransaction $transaction, TransactionEngagementService $engagementService)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $transaction->politician_id === (int) $politician->id, 403);

        $activePaymentMode = $this->activePaymentMode();
        $txMode = $transaction->metadata['payment_mode'] ?? null;

        if ($activePaymentMode && $txMode && $txMode !== $activePaymentMode) {
            abort(404);
        }

        if ($transaction->transaction_type !== 'charge' || $transaction->status !== 'succeeded') {
            return response()->json([
                'message' => 'Invoice engagement details are available only for succeeded credit purchases.',
            ], 422);
        }

        $snapshot = $engagementService->aggregateForInvoice($transaction, $politician, $activePaymentMode);

        return response()->json([
            'data' => $snapshot,
        ]);
    }

    /**
     * Update the receipt email address for a politician.
     * Allows specifying a different email for receipt delivery (e.g., when using someone else's card).
     */
    public function updateReceiptEmail(Request $request)
    {
        $request->validate([
            'receipt_email' => ['nullable', 'email'],
        ]);

        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $politician->update([
            'receipt_email' => $request->input('receipt_email') ?: null,
        ]);

        return back()->with('success', 'Receipt email updated successfully.');
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
        $idmeConfigured = (string) config('services.idme.client_id', '') !== ''
            && (string) config('services.idme.client_secret', '') !== '';

        if ($idmeConfigured) {
            return back()->withErrors([
                'kyc_document' => 'Manual KYC uploads are disabled while Id.me verification is enabled.',
            ]);
        }

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
            ->forActiveStripeMode()
            ->with('referredVoter')
            ->latest()
            ->take(30)
            ->get();

        // Procurement commissions (politician_procurement type earned by this politician)
        $procurementEarnings = $politician->referralEarnings()
            ->procurements()
            ->forActiveStripeMode()
            ->with('politician')
            ->latest()
            ->get();

        $totalVoterViewEarnings  = (float) $politician->referralEarnings()->voterViews()->forActiveStripeMode()->sum('commission_amount');
        $totalProcurementEarnings = (float) $politician->referralEarnings()->procurements()->forActiveStripeMode()->sum('commission_amount');

        $visitQuery = ReferralVisit::where('referrer_politician_id', $politician->id);
        $totalReferralVisits = (clone $visitQuery)->count();
        $uniqueReferralVisitors = (clone $visitQuery)
            ->whereNotNull('session_id')
            ->distinct('session_id')
            ->count('session_id');
        $referralConversions = (clone $visitQuery)->whereNotNull('converted_at')->count();
        $referralConversionRate = $totalReferralVisits > 0
            ? round(($referralConversions / $totalReferralVisits) * 100, 1)
            : 0.0;

        return view('standalone.politician.referrals', compact(
            'politician',
            'referredVoters',
            'referredPoliticians',
            'voterViewEarnings',
            'procurementEarnings',
            'totalVoterViewEarnings',
            'totalProcurementEarnings',
            'totalReferralVisits',
            'uniqueReferralVisitors',
            'referralConversions',
            'referralConversionRate'
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

