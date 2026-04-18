<?php

namespace App\Mail;

use App\Models\PoliticalCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class CampaignQuestionDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly PoliticalCampaign $campaign,
        public readonly Collection $questions,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Campaign ended — voter question digest for "' . $this->campaign->title . '"',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-question-digest',
            text: 'emails.campaign-question-digest-text',
        );
    }
}
