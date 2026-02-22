<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PayoutProcessedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly float $amount,
        public readonly int $viewCount,
        public readonly string $payoutMethod = 'PayPal',
        public readonly string $periodLabel = ''
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '💰 Payout Processed — $' . number_format($this->amount, 2) . ' sent to you',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlView: 'emails.payout-processed',
            textView: 'emails.payout-processed-text',
        );
    }
}
