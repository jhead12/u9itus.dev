<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Mail\CampaignApprovedMail;
use App\Mail\CampaignRejectedMail;
use App\Services\ReverbBroadcastService;
use App\Mail\KycApprovedMail;
use App\Mail\KycRejectedMail;
use App\Models\CampaignAuditLog;
use App\Models\EmailTemplate;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
     * Show the admin dashboard.
     */
    public function dashboard()
    {
        $stats = [
            'total_users'       => User::count(),
            'total_politicians' => User::where('user_type', 'politician')->count(),
            'total_voters'      => User::where('user_type', 'voter')->count(),
            'pending_campaigns' => PoliticalCampaign::where('approval_status', 'pending')->count(),
            'total_campaigns'   => PoliticalCampaign::count(),
            'active_campaigns'  => PoliticalCampaign::where('status', 'active')->count(),
            'total_views'       => ViewSession::where('status', 'completed')->count(),
            'total_revenue'     => ViewSession::where('status', 'completed')->sum('platform_revenue') ?? 0,
            'total_payouts'     => ViewSession::where('status', 'completed')->sum('voter_payout_amount') ?? 0,
            'kyc_pending'       => User::where('kyc_status', 'pending')
                                        ->whereIn('user_type', ['politician', 'voter'])->count(),
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

        $summary = [
            'total_active'    => PoliticalCampaign::where('status', CampaignStatus::Active->value)->count(),
            'total_scheduled' => PoliticalCampaign::where('status', CampaignStatus::Scheduled->value)->count(),
            'total_paused'    => PoliticalCampaign::where('status', CampaignStatus::Paused->value)->count(),
            'total_spend'     => PoliticalCampaign::whereIn('status', [CampaignStatus::Active->value, CampaignStatus::Paused->value, CampaignStatus::Scheduled->value])->sum('amount_spent'),
            'total_views'     => PoliticalCampaign::whereIn('status', [CampaignStatus::Active->value, CampaignStatus::Paused->value, CampaignStatus::Scheduled->value])->sum('views_completed'),
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
            'campaign_type'            => ['required', 'in:video,live_feed'],
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
    public function users()
    {
        $users = User::with(['politician', 'voter'])
            ->latest()
            ->paginate(30);

        return view('standalone.admin.users', compact('users'));
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

        $user->update([
            'kyc_status'      => 'approved',
            'is_verified'     => true,
            'kyc_reviewed_at' => now(),
            'kyc_reviewer_id' => auth()->id(),
        ]);

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

        $user->update([
            'kyc_status'           => 'rejected',
            'kyc_reviewed_at'      => now(),
            'kyc_reviewer_id'      => auth()->id(),
            'kyc_rejection_reason' => $request->input('reason', 'Identity could not be verified.'),
        ]);

        if ($user->politician) {
            $user->politician->update(['kyc_status' => 'rejected']);
        }

        // Notify the user their KYC has been rejected with the reason
        try {
            Mail::to($user->email)->queue(new KycRejectedMail(
                $user,
                $request->input('reason', 'Identity could not be verified.')
            ));
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Failed to send KYC rejected email', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'KYC rejected for ' . $user->name . '.');
    }

    /**
     * Show analytics dashboard.
     */
    public function analytics()
    {
        $stats = [
            'total_views'    => ViewSession::where('status', 'completed')->count(),
            'total_revenue'  => ViewSession::where('status', 'completed')->sum('platform_revenue') ?? 0,
            'total_payouts'  => ViewSession::where('status', 'completed')->sum('voter_payout_amount') ?? 0,
            'total_profit'   => (ViewSession::where('status', 'completed')->sum('platform_revenue') ?? 0)
                                - (ViewSession::where('status', 'completed')->sum('voter_payout_amount') ?? 0),
            'total_campaigns' => PoliticalCampaign::count(),
            'active_campaigns' => PoliticalCampaign::where('status', 'active')->count(),
        ];

        return view('standalone.admin.analytics', compact('stats'));
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
    public function engagementReport()
    {
        $engagement = [
            'total_sessions'     => ViewSession::count(),
            'completed_sessions' => ViewSession::where('status', 'completed')->count(),
            'flagged_sessions'   => ViewSession::where('fraud_score', '>', 50)->count(),
            'avg_watch_percent'  => ViewSession::where('status', 'completed')->avg('completion_percentage') ?? 0,
        ];

        return view('standalone.admin.reports-engagement', compact('engagement'));
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

        return view('standalone.admin.settings', compact('smtp'));
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
            'kyc_approved'      => 'emails.kyc-approved',
            'kyc_rejected'      => 'emails.kyc-rejected',
            'campaign_approved' => 'emails.campaign-approved',
            'campaign_rejected' => 'emails.campaign-rejected',
            'campaign_completed'=> 'emails.campaign-completed',
            'credits_purchased' => 'emails.credits-purchased',
            'low_balance_alert' => 'emails.low-balance-alert',
            'payout_processed'  => 'emails.payout-processed',
            'welcome'           => 'emails.welcome',
            'admin_new_user'    => 'emails.admin-new-user',
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

        return view('standalone.admin.profile', compact('user'));
    }

    /**
     * Update admin name, email, or password.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name'                  => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'current_password'      => ['nullable', 'current_password'],
            'password'              => ['nullable', 'min:8', 'confirmed'],
        ]);

        $user->name  = $validated['name'];
        $user->email = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }
}
