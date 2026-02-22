<?php

namespace App\Mail;

use App\Models\PoliticalCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PoliticalCampaign $campaign,
        public readonly int $totalViews,
        public readonly float $totalSpent
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🏁 Campaign Completed — "' . $this->campaign->title . '"',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlView: 'emails.campaign-completed',
            textView: 'emails.campaign-completed-text',
        );
    }
}
