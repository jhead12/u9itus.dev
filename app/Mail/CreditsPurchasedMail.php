<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CreditsPurchasedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly float $amount,
        public readonly int $credits,
        public readonly float $newBalance,
        public readonly string $transactionId = ''
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '💳 Credits Added — ' . $this->credits . ' views purchased',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credits-purchased',
            text: 'emails.credits-purchased-text',
        );
    }
}
