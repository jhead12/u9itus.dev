<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CreditsRefundedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly float $amount,
        public readonly float $refundedCredits,
        public readonly float $newBalance,
        public readonly string $transactionId = '',
        public readonly ?string $reason = null,
    ) {
    }

    public function envelope(): Envelope
    {
        $default = 'Refund Processed - $' . number_format($this->amount, 2) . ' returned';
        $template = EmailTemplate::forKey('credits_refunded');

        return new Envelope(
            subject: $template?->effectiveSubject($default) ?? $default,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.credits-refunded',
            text: 'emails.credits-refunded-text',
        );
    }
}
