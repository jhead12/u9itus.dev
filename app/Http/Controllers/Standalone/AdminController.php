<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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
        return view('standalone.admin.dashboard');
    }

    /**
     * Show pending campaigns for approval.
     */
    public function pendingCampaigns()
    {
        return view('standalone.admin.campaigns-pending');
    }

    /**
     * Approve a campaign.
     */
    public function approveCampaign($campaignId)
    {
        return back()->with('success', 'Campaign approved.');
    }

    /**
     * Reject a campaign.
     */
    public function rejectCampaign(Request $request, $campaignId)
    {
        return back()->with('success', 'Campaign rejected.');
    }

    /**
     * List all users.
     */
    public function users()
    {
        return view('standalone.admin.users');
    }

    /**
     * Show user details.
     */
    public function showUser($userId)
    {
        return view('standalone.admin.user-details');
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
        return view('standalone.admin.fraud');
    }

    /**
     * Show flagged views.
     */
    public function flaggedViews()
    {
        return view('standalone.admin.fraud-views');
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
        return view('standalone.admin.payouts');
    }

    /**
     * Show pending payouts.
     */
    public function pendingPayouts()
    {
        return view('standalone.admin.payouts-pending');
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
        return view('standalone.admin.analytics');
    }

    /**
     * Show revenue report.
     */
    public function revenueReport()
    {
        return view('standalone.admin.reports-revenue');
    }

    /**
     * Show engagement report.
     */
    public function engagementReport()
    {
        return view('standalone.admin.reports-engagement');
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
