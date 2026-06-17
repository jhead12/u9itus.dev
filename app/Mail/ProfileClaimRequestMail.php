<?php

namespace App\Mail;

use App\Models\Politician;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the claimant's submitted email with a one-time verification link.
 */
class ProfileClaimRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Politician $politician,
        public readonly string $claimUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Verify your request to claim ' . $this->politician->full_name . "'s U9itus profile",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.profile-claim-request',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
