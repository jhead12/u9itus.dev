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
 * Sent when a guest (not logged in) opts into the weekly "saved places"
 * digest via the inline map prompt. No digest content is ever sent until
 * this link is clicked — see GuestDigestOptInController::confirm().
 *
 * Admin-configurable via the `guest_digest_confirmation` email template.
 */
class GuestDigestConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public const TEMPLATE_KEY = 'guest_digest_confirmation';

    public function __construct(
        public readonly Voter $voter,
        public readonly string $confirmUrl,
    ) {
    }

    public static function isEnabled(): bool
    {
        $template = EmailTemplate::forKey(self::TEMPLATE_KEY);

        return $template === null || $template->is_active;
    }

    public function envelope(): Envelope
    {
        $defaultSubject = 'Confirm your weekly saved-places updates';
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
                ['{{voter.full_name}}'],
                [(string) $this->voter->full_name],
                $template->body_override
            );

            return new Content(
                view: 'emails.template-override',
                with: ['html' => $html],
            );
        }

        return new Content(
            view: 'emails.guest-digest-confirmation',
            text: 'emails.guest-digest-confirmation-text',
        );
    }
}
