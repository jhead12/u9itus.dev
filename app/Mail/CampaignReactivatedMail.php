<?php

namespace App\Mail;

use App\Models\PoliticalCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignReactivatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly PoliticalCampaign $campaign)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Campaign Reactivated - "' . $this->campaign->title . '"',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-reactivated',
            text: 'emails.campaign-reactivated-text',
        );
    }
}
