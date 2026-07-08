<?php

namespace App\Mail;

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
 * Queued fire-and-forget — a failure to send must NOT block the webhook response.
 */
class VoterVerifiedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Voter $voter,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Your identity has been verified — you\'re ready to earn',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.voter-verified',
            text: 'emails.voter-verified-text',
        );
    }
}
