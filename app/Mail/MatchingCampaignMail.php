<?php

namespace App\Mail;

use App\Models\Cause;
use App\Models\PoliticalCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MatchingCampaignMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PoliticalCampaign $campaign,
        public readonly Cause $cause,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'A campaign matches a cause you favorited: "' . $this->cause->title . '"',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.matching-campaign',
            text: 'emails.matching-campaign-text',
        );
    }
}
