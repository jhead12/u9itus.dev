<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KycRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $reason = 'Identity could not be verified.'
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Identity Verification — Action Required',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlView: 'emails.kyc-rejected',
            textView: 'emails.kyc-rejected-text',
        );
    }
}
