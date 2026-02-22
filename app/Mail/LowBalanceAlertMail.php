<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowBalanceAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly float $currentBalance,
        public readonly int $remainingViews,
        public readonly string $campaignTitle = ''
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '⚠️ Low Credit Balance — Add funds to keep campaigns running',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlView: 'emails.low-balance-alert',
            textView: 'emails.low-balance-alert-text',
        );
    }
}
