<?php

namespace App\Mail;

use App\Models\CivicEvent;
use App\Models\EventRsvp;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventRsvpApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CivicEvent $event,
        public readonly EventRsvp $rsvp,
    ) {
    }

    public function envelope(): Envelope
    {
        $defaultSubject = "✅ You're confirmed for \"{$this->event->title}\"";
        $template = EmailTemplate::forKey('event_rsvp_approved');

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('event_rsvp_approved');

        if ($template?->is_active && $template->hasBodyOverride()) {
            return new Content(
                view: 'emails.template-override',
                with: ['html' => $template->body_override],
            );
        }

        return new Content(
            view: 'emails.event-rsvp-approved',
            text: 'emails.event-rsvp-approved-text',
            with: [
                'event' => $this->event,
                'rsvp' => $this->rsvp,
            ],
        );
    }
}
