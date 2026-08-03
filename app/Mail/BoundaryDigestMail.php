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
 * Weekly digest of new candidate activity (news, endorsements) for the
 * districts/cities a voter has favorited on the map — see
 * App\Services\BoundaryDigestMatchService and
 * App\Console\Commands\SendWeeklyBoundaryDigest.
 *
 * Admin-configurable via the `boundary_digest` email template — subject
 * override + is_active kill switch only (no body_override support: the
 * content is data-driven per-voter, unlike the static templates that support
 * full-body admin overrides).
 */
class BoundaryDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public const TEMPLATE_KEY = 'boundary_digest';

    public function __construct(
        public readonly Voter $voter,
        public readonly array $sections,
        public readonly string $periodLabel,
    ) {
    }

    public static function isEnabled(): bool
    {
        $template = EmailTemplate::forKey(self::TEMPLATE_KEY);

        return $template === null || $template->is_active;
    }

    public function envelope(): Envelope
    {
        $defaultSubject = 'Your weekly saved-places update';
        $template = EmailTemplate::forKey(self::TEMPLATE_KEY);

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.boundary-digest',
            text: 'emails.boundary-digest-text',
        );
    }
}
