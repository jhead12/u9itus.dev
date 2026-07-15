<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Concerns\HandlesCampaignVideoUpload;
use App\Http\Controllers\Concerns\PaymentModeFilterable;
use App\Http\Controllers\Controller;
use App\Mail\CampaignReactivatedMail;
use App\Models\CampaignAuditLog;
use App\Models\CampaignTransaction;
use App\Models\CitizenCampaign;
use App\Models\PoliticalCampaign;
use App\Models\PoliticianCredit;
use App\Notifications\CampaignStatusChangedNotification;
use App\Services\CampaignModerationService;
use App\Services\CampaignQandAService;
use App\Services\CampaignQuestionDigestService;
use App\Services\CampaignStatusNotifier;
use App\Services\PoliticalPaymentService;
use App\Services\ReverbBroadcastService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Admin political-campaign moderation and lifecycle.
 *
 * Split out of AdminController. Covers the pending/running campaign queues,
 * approve/reject, edit/update (incl. video upload handling), stop/reactivate,
 * bulk actions, and the per-campaign audit log. Scopes running campaigns to
 * the active Stripe payment mode and carries the four campaign helpers.
 */
class AdminCampaignController extends Controller
{
    use HandlesCampaignVideoUpload, PaymentModeFilterable;

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
}
