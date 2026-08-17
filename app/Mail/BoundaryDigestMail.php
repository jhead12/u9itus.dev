<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Voter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

/**
 * Digest of new candidate activity (news, endorsements, popular YouTube
 * clips) for the districts/cities a voter has favorited on the map — see
 * App\Services\BoundaryDigestMatchService and
 * App\Console\Commands\SendBoundaryDigest. Sent on a content-driven cadence,
 * not a fixed calendar day — see SendBoundaryDigest for the eligibility rule.
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
        public readonly int $remainingCount = 0,
    ) {
    }

    public static function isEnabled(): bool
    {
        $template = EmailTemplate::forKey(self::TEMPLATE_KEY);

        return $template === null || $template->is_active;
    }

    public function envelope(): Envelope
    {
        $defaultSubject = 'Your saved-places update';
        $template = EmailTemplate::forKey(self::TEMPLATE_KEY);

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    public function content(): Content
    {
        // Guest (user_id-null) recipients have no account/settings page to
        // manage this from, so they get a one-click unsubscribe link instead
        // — see GuestDigestOptInController::unsubscribe().
        $unsubscribeUrl = $this->voter->user_id === null
            ? URL::signedRoute('map.boundaries.digest.unsubscribe', [
                'voter' => $this->voter->uuid,
                'hash' => sha1((string) $this->voter->email),
            ])
            : null;

        return new Content(
            view: 'emails.boundary-digest',
            text: 'emails.boundary-digest-text',
            with: ['unsubscribeUrl' => $unsubscribeUrl, 'remainingCount' => $this->remainingCount],
        );
    }
}
