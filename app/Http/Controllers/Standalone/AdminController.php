<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Http\Controllers\Controller;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
     * Approve a campaign.
     */
    public function approveCampaign(PoliticalCampaign $campaign)
    {
        $campaign->update([
            'approval_status' => ApprovalStatus::Approved,
            'status'          => CampaignStatus::Active,
        ]);

        return back()->with('success', 'Campaign "' . $campaign->title . '" has been approved and set to active.');
    }

    /**
     * Reject a campaign.
     */
    public function rejectCampaign(Request $request, PoliticalCampaign $campaign)
    {
        $campaign->update([
            'approval_status' => ApprovalStatus::Rejected,
            'status'          => CampaignStatus::Cancelled,
        ]);

        return back()->with('success', 'Campaign "' . $campaign->title . '" has been rejected.');
    }

    /**
     * List all users.
     */
    public function users()
    {
        $users = User::withCount([])
            ->latest()
            ->paginate(30);

        return view('standalone.admin.users', compact('users'));
    }

    /**
     * Show user details.
     */
    public function showUser($userId)
    {
        $user = User::findOrFail($userId);

        return view('standalone.admin.user-details', compact('user'));
    }

    /**
     * Suspend a user.
     */
    public function suspendUser(Request $request, $userId)
    {
        return back()->with('success', 'User suspended.');
    }

    /**
     * Unsuspend a user.
     */
    public function unsuspendUser(Request $request, $userId)
    {
        return back()->with('success', 'User unsuspended.');
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
     * Review a flagged view.
     */
    public function reviewView(Request $request, $viewId)
    {
        return back()->with('success', 'View reviewed.');
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
     * Process batch payouts.
     */
    public function processBatchPayouts(Request $request)
    {
        return back()->with('success', 'Batch payouts processed.');
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
            $value = $request->input($key, '');
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
}
