<?php

namespace App\Mail;

use App\Models\Voter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to a voter immediately after they register through an Early-bank
 * referral link. Confirms that the voter is enrolled in the Early-bank
 * referral program and that their referrer will earn the standard $10
 * referral bonus + 10% commission on the voter's earnings.
 *
 * Fire-and-forget — failure to send must NOT block voter registration.
 */
class EarlyBankReferralEnrolledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Voter $voter,
        public readonly string $earlybankMemberId,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '✅ You\'re enrolled in the Early-bank referral program',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.earlybank-referral-enrolled',
            text: 'emails.earlybank-referral-enrolled-text',
        );
    }
}
