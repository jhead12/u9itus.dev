<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Admin email-template management: list, edit, update, toggle, and preview.
 *
 * Split out of AdminController. previewEmailTemplate renders either a
 * built-in referral/share card, the template's body override, or the
 * backing Blade view with sample data, so admins can see the final email
 * without sending it.
 */
class AdminEmailTemplateController extends Controller
{
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
}
