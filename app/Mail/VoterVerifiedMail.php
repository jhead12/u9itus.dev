<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Voter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a voter when their Authentic User Verifier (Stripe Connect) identity
 * verification is confirmed active — i.e., when the Stripe account.updated
 * webhook transitions their account to charges_enabled + payouts_enabled.
 *
 * Admin-configurable via the `voter_verified` email template (subject/body
 * override + is_active kill switch, managed at /admin/email-templates).
 *
 * Queued fire-and-forget — a failure to send must NOT block the webhook response.
 */
class VoterVerifiedMail extends Mailable
{
    use Queueable, SerializesModels;

    public const TEMPLATE_KEY = 'voter_verified';

    public function __construct(
        public readonly Voter $voter,
    ) {}

    /**
     * Whether admins have disabled this notification entirely.
     */
    public static function isEnabled(): bool
    {
        $template = EmailTemplate::forKey(self::TEMPLATE_KEY);

        // Template row not seeded yet → default to enabled.
        return $template === null || $template->is_active;
    }

    public function envelope(): Envelope
    {
        $defaultSubject = '✅ Your identity has been verified — you\'re ready to earn';
        $template = EmailTemplate::forKey(self::TEMPLATE_KEY);

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey(self::TEMPLATE_KEY);

        if ($template?->is_active && $template->hasBodyOverride()) {
            $html = str_replace(
                ['{{voter.full_name}}', '{{voter.uuid}}'],
                [(string) $this->voter->full_name, (string) $this->voter->uuid],
                $template->body_override
            );

            return new Content(
                view: 'emails.template-override',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.voter-verified',
            text: 'emails.voter-verified-text',
        );
    }
}
