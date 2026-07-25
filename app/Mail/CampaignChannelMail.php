<?php

namespace App\Mail;

use App\Services\Marketing\DispatchPayload;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The campaign message delivered through the first-party email channel.
 *
 * Built from a DispatchPayload (not a full campaign model) so the channel
 * stays decoupled from the political/citizen campaign shapes — the payload
 * already carries the title, summary, and a CTA URL. This is the template
 * every email-channel dispatch renders; per-campaign overrides can later
 * ride through the payload's channelConfig.
 */
class CampaignChannelMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly DispatchPayload $payload)
    {
    }

    public function envelope(): Envelope
    {
        $title = $this->payload->campaign['title'] ?? 'A message from U9itus';

        return new Envelope(
            subject: 'New campaign: ' . $title,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.campaign-channel',
            text: 'emails.campaign-channel-text',
            with: [
                'campaign' => $this->payload->campaign,
                'recipient' => $this->payload->recipient,
            ],
        );
    }
}