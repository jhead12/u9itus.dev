<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to someone who asked to be notified when a state's ballot measures
 * became available, once BackfillStateElectionData flips that state to `ready`.
 */
class StateBallotMeasuresReadyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $state,
        public readonly int $count,
        public readonly string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ballot measures for {$this->state} are now available",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.state-ballot-measures-ready',
            text: 'emails.state-ballot-measures-ready-text',
        );
    }
}
