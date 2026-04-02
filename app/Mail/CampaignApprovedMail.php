<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\PoliticalCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CampaignApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public readonly PoliticalCampaign $campaign)
    {
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $defaultSubject = '✅ Campaign Approved — "' . $this->campaign->title . '"';
        $template = EmailTemplate::forKey('campaign_approved');

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $template = EmailTemplate::forKey('campaign_approved');

        if ($template?->is_active && $template->hasBodyOverride()) {
            return new Content(
                view: 'emails.template-override',
                with: ['html' => $template->body_override],
            );
        }

        return new Content(
            view: 'emails.campaign-approved',
            text: 'emails.campaign-approved-text',
        );
    }
}
