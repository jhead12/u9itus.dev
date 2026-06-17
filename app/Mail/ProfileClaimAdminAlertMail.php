<?php

namespace App\Mail;

use App\Models\Politician;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Notifies the platform admin when a verified claim token has been consumed
 * and a new politician account is ready for review.
 */
class ProfileClaimAdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Politician $politician,
        public readonly string $claimantEmail,
        public readonly string $adminProfileUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🏛 Profile Claimed — ' . $this->politician->full_name . ' (' . $this->politician->state . ')',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile-claim-admin-alert',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
