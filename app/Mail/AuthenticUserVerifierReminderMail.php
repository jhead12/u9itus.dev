<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuthenticUserVerifierReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $payoutUrl,
        public readonly string $startUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Action required: Complete Authentic User Verifier for payouts',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.authentic-user-verifier-reminder',
            text: 'emails.authentic-user-verifier-reminder-text',
        );
    }
}
