<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Jobs\MatchPoliticianToElectionData;
use App\Mail\CampaignApprovedMail;
use App\Mail\CampaignReactivatedMail;
use App\Mail\CampaignRejectedMail;
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
use App\Models\PoliticalCampaign;
use App\Models\PoliticianCredit;
use App\Models\Politician;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\VoterWatchReport;
use App\Models\Voter;
use App\Notifications\CampaignStatusChangedNotification;
use App\Notifications\SystemAnnouncementNotification;
use App\Services\AdminTwoFactorService;
use App\Services\CampaignBillingService;
use App\Services\PlatformSettingsService;
use App\Services\StripePaymentService;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    /**
     * Active app payment mode derived from configured Stripe secret.
     */
    private function activePaymentMode(): ?string
    {
        $mode = app(StripePaymentService::class)->configuredMode();
        return in_array($mode, ['live', 'test'], true) ? $mode : null;
    }

    /**
     * Apply mode-aware filter to transaction queries.
     */
    private function applyPaymentModeFilter($query, ?string $mode)
    {
        if (! $mode) {
            return $query;
        }

        return $query->where('metadata->payment_mode', $mode);
    }

    /**
     * Campaign ids that have transaction activity in the active payment mode.
     */
    private function modeScopedCampaignIds(?string $mode)
    {
        return $this->applyPaymentModeFilter(
            CampaignTransaction::query()->select('campaign_id')->whereNotNull('campaign_id')->distinct(),
            $mode
        );
    }

    /**
     * Politician ids that have billing activity in the active payment mode.
     * Used to ensure campaign monitoring reflects the currently configured Stripe mode.
     */
    private function modeScopedPoliticianIds(?string $mode)
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
     * Show the admin dashboard.
     */
    public function dashboard()
    {
        $activePaymentMode = $this->activePaymentMode();
        $campaignIds = $this->modeScopedCampaignIds($activePaymentMode);
        $completedViewQuery = ViewSession::where('status', 'completed')
            ->whereIn('political_campaign_id', $campaignIds);

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
                                        ->whereIn('user_type', ['politician', 'voter'])->count(),
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
     * If the campaign has a scheduled_start_at in the future it is placed in
     * 'scheduled' status so the campaigns:apply-schedule command activates it
     * at the right time.  Otherwise it goes straight to 'active'.
     */
    public function approveCampaign(PoliticalCampaign $campaign)
    {
        $scheduledStart = $campaign->scheduled_start_at;
        $newStatus = ($scheduledStart && $scheduledStart->isFuture())
            ? CampaignStatus::Scheduled
            : CampaignStatus::Active;

        $campaign->update([
            'approval_status' => ApprovalStatus::Approved,
            'status'          => $newStatus,
        ]);

        CampaignAuditLog::create([
            'campaign_id' => $campaign->id,
            'admin_id'    => auth()->id(),
            'action'      => 'approved',
        ]);

        // Notify the politician that their campaign was approved
        try {
            $politicianUser = $campaign->politician?->user;
            if ($politicianUser?->email) {
                Mail::to($politicianUser->email)
                    ->queue(new CampaignApprovedMail($campaign));
            }
        } catch (\Exception) {
            // Non-fatal — silently skip if mail not configured
        }

        // Phase 11 — real-time WebSocket push to politician dashboard
        app(ReverbBroadcastService::class)->campaignApproved($campaign);

        $label = $newStatus === CampaignStatus::Scheduled
            ? 'approved and scheduled (activates ' . $scheduledStart->format('M j, Y H:i') . ')'
            : 'approved and set to active';

        return back()->with('success', 'Campaign "' . $campaign->title . '" has been ' . $label . '.');
    }

    /**
     * Reject a campaign.
     */
    public function rejectCampaign(Request $request, PoliticalCampaign $campaign)
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $rejectionReason = $request->input('reason', 'Does not meet content guidelines.');

        $campaign->update([
            'approval_status'  => ApprovalStatus::Rejected,
            'status'           => CampaignStatus::Cancelled,
            'rejection_reason' => $rejectionReason,
        ]);

        CampaignAuditLog::create([
            'campaign_id' => $campaign->id,
            'admin_id'    => auth()->id(),
            'action'      => 'rejected',
            'reason'      => $rejectionReason,
        ]);

        // Notify the politician that their campaign was rejected
        try {
            $politicianUser = $campaign->politician?->user;
            if ($politicianUser?->email) {
                Mail::to($politicianUser->email)
                    ->queue(new CampaignRejectedMail($campaign, $rejectionReason));
            }
        } catch (\Exception) {
            // Non-fatal — silently skip if mail not configured
        }

        // Phase 11 — real-time WebSocket push to politician dashboard
        app(ReverbBroadcastService::class)->campaignRejected($campaign, $rejectionReason);

        return back()->with('success', 'Campaign "' . $campaign->title . '" has been rejected.');
    }

    /**
     * Show the admin edit form for any campaign.
     */
    public function editCampaign(PoliticalCampaign $campaign)
    {
        $campaign->load('politician.user');

        $auditLogs = CampaignAuditLog::where('campaign_id', $campaign->id)
            ->with('admin:id,name')
            ->latest()
            ->get();

        $states = config('u9itus.us_states', []);
        $governanceLevels = config('u9itus.governance_levels', [
            'Federal' => 'Federal', 'State' => 'State', 'County' => 'County',
            'City' => 'City', 'School Board' => 'School Board',
        ]);

        return view('standalone.admin.campaign-edit', compact('campaign', 'states', 'governanceLevels', 'auditLogs'));
    }

    /**
     * Update a campaign as admin (no status/ownership restrictions).
     * Diffs all changed fields and writes an immutable audit log entry.
     */
    public function updateCampaign(Request $request, PoliticalCampaign $campaign)
    {
        $validated = $request->validate([
            'title'                    => ['required', 'string', 'max:255'],
            'message_summary'          => ['nullable', 'string', 'max:2000'],
            'campaign_type'            => ['required', 'in:video,live_feed,q_and_a'],
            'governance_level'         => ['nullable', 'string', 'max:100'],
            'total_budget'             => ['required', 'numeric', 'min:0'],
            'total_views_requested'    => ['required', 'integer', 'min:0'],
            'target_states'            => ['nullable', 'array'],
            'target_states.*'          => ['string', 'max:2'],
            'target_cities'            => ['nullable', 'array'],
            'target_cities.*'          => ['string', 'max:100'],
            'media_url'                => ['nullable', 'url'],
            'media_duration'           => ['nullable', 'integer', 'min:1'],
            'live_feed_url'            => ['nullable', 'url'],
            'live_scheduled_at'        => ['nullable', 'date'],
            'min_watch_time_percent'   => ['nullable', 'integer', 'min:50', 'max:100'],
            'status'                   => ['required', 'in:draft,pending_approval,scheduled,active,paused,completed,cancelled'],
            'approval_status'          => ['required', 'in:pending,approved,rejected'],
            'rejection_reason'         => ['nullable', 'string', 'max:500'],
            'edit_reason'              => ['nullable', 'string', 'max:500'],
        ]);

        // Snapshot pre-update values for the diff (raw attributes, not cast)
        $trackFields = array_diff(array_keys($validated), ['edit_reason']);
        $before  = $campaign->only($trackFields);
        $reason  = $validated['edit_reason'] ?? null;
        unset($validated['edit_reason']);

        $campaign->update($validated);

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

        // Phase 11 — real-time WebSocket push to politician dashboard
        app(ReverbBroadcastService::class)->campaignStopped($campaign, $request->input('reason'));

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

        $allowedRoles = ['admin', 'politician', 'voter'];
        $allowedKycStatuses = ['approved', 'pending', 'rejected'];
        $allowedAccountStatuses = ['active', 'unverified', 'suspended'];

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
        $reviewedAt = now();

        foreach ($users as $user) {
            if ($user->user_type === 'admin' && in_array($action, ['suspend', 'kyc_approve', 'kyc_reject'], true)) {
                $skippedAdmins++;
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

            return back()->withErrors(['error' => $noneAppliedMessage]);
        }

        $message = $updated . ' user(s) ' . $labels[$action] . '.';

        if ($skippedAdmins > 0) {
            $message .= ' ' . $skippedAdmins . ' admin account(s) skipped.';
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
        $query = CandidateMatchReview::with(['politician.user', 'candidateRecord'])
            ->where('status', CandidateMatchReview::STATUS_PENDING)
            ->latest();

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

        return view('standalone.admin.candidate-match-reviews', compact('reviews', 'stats'));
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
        $stats = [
            'pending_amount' => ViewSession::where('status', 'completed')
                ->where('payment_status', 'pending')->sum('voter_payout_amount') ?? 0,
            'paid_amount'    => ViewSession::where('payment_status', 'paid')->sum('voter_payout_amount') ?? 0,
            'pending_count'  => ViewSession::where('status', 'completed')
                ->where('payment_status', 'pending')->count(),
        ];

        return view('standalone.admin.payouts', compact('stats'));
    }

    /**
     * Show pending payouts.
     */
    public function pendingPayouts()
    {
        $sessions = ViewSession::with(['voter', 'campaign'])
            ->where('status', 'completed')
            ->where('payment_status', 'pending')
            ->latest()
            ->paginate(30);

        return view('standalone.admin.payouts-pending', compact('sessions'));
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
            $results = $paymentService->processBatchPayouts();
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
        $users = User::with(['politician', 'voter'])
            ->where('kyc_status', 'pending')
            ->whereIn('user_type', ['politician', 'voter'])
            ->latest()
            ->paginate(30);

        $stats = [
            'pending'  => User::where('kyc_status', 'pending')
                               ->whereIn('user_type', ['politician', 'voter'])->count(),
            'approved' => User::where('kyc_status', 'approved')
                               ->whereIn('user_type', ['politician', 'voter'])->count(),
            'rejected' => User::where('kyc_status', 'rejected')
                               ->whereIn('user_type', ['politician', 'voter'])->count(),
        ];

        return view('standalone.admin.kyc', compact('users', 'stats'));
    }

    /**
     * Approve a user's KYC.
     */
    public function approveKyc(Request $request, $userId)
    {
        $user = User::with(['politician', 'voter'])->findOrFail($userId);

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
        $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user = User::with(['politician', 'voter'])->findOrFail($userId);

        try {
            $reason = $request->input('reason', 'Identity could not be verified.');

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

            $user->update($userUpdate);

            if ($user->politician) {
                try {
                    if (Schema::hasColumn('politicians', 'kyc_status')) {
                        $user->politician->update(['kyc_status' => 'rejected']);
                    }
                } catch (\Exception $e) {
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
            } catch (\Exception $e) {
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

            return back()->withErrors(['error' => 'Unable to reject KYC right now. Please try again.']);
        }

        return back()->with('success', 'KYC rejected for ' . $user->name . '.');
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
        $completedViewQuery = ViewSession::where('status', 'completed')
            ->whereIn('political_campaign_id', $campaignIds);

        $stats = [
            'total_views'    => (clone $completedViewQuery)->count(),
            'total_revenue'  => (clone $completedViewQuery)->sum('platform_revenue') ?? 0,
            'total_payouts'  => (clone $completedViewQuery)->sum('voter_payout_amount') ?? 0,
            'total_profit'   => ((clone $completedViewQuery)->sum('platform_revenue') ?? 0)
                                - ((clone $completedViewQuery)->sum('voter_payout_amount') ?? 0),
            'total_campaigns' => PoliticalCampaign::whereIn('id', $campaignIds)->count(),
            'active_campaigns' => PoliticalCampaign::where('status', 'active')->whereIn('id', $campaignIds)->count(),
        ];

        return view('standalone.admin.analytics', compact('stats', 'activePaymentMode'));
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
     * Show revenue report.
     */
    public function revenueReport()
    {
        $revenue = [
            'total'   => ViewSession::where('status', 'completed')->sum('platform_revenue') ?? 0,
            'payouts' => ViewSession::where('status', 'completed')->sum('voter_payout_amount') ?? 0,
            'profit'  => (ViewSession::where('status', 'completed')->sum('platform_revenue') ?? 0)
                         - (ViewSession::where('status', 'completed')->sum('voter_payout_amount') ?? 0),
        ];

        return view('standalone.admin.reports-revenue', compact('revenue'));
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
        ];

        return view('standalone.admin.reports-engagement', compact(
            'engagement',
            'questionStatus',
            'questionStats',
            'recentQuestions',
            'days',
            'surveyOptionBreakdown',
            'surveyCampaignBreakdown'
        ));
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

        return view('standalone.admin.settings', compact('smtp', 'adminTwoFactorEnforced'));
    }

    /**
     * Update global security settings controlled from the admin settings page.
     */
    public function updateSecuritySettings(Request $request)
    {
        $validated = $request->validate([
            'admin_2fa_enforced' => ['required', 'boolean'],
        ]);

        $newValue = (bool) $validated['admin_2fa_enforced'];
        $currentValue = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        PlatformSettingsService::set('admin_2fa_enforced', $newValue, [
            'description' => 'Global policy toggle requiring TOTP for all admin logins.',
            'category' => 'general',
        ]);

        Log::info('Admin security policy updated', [
            'admin_id' => auth()->id(),
            'key' => 'admin_2fa_enforced',
            'old_value' => $currentValue,
            'new_value' => $newValue,
        ]);

        AdminSecurityAuditLog::record(
            $request->user(),
            'policy.admin_2fa.updated',
            [
                'old_value' => $currentValue,
                'new_value' => $newValue,
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
}
