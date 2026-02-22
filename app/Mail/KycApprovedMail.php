<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class KycApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ Identity Verified — Welcome to ' . config('app.name', 'U9itus'),
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlView: 'emails.kyc-approved',
            textView: 'emails.kyc-approved-text',
        );
    }
}
