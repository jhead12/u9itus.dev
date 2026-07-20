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

class EventHostRsvpMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CivicEvent $event,
        public readonly EventRsvp $rsvp,
    ) {
    }

    public function envelope(): Envelope
    {
        $defaultSubject = "🎟️ New RSVP for \"{$this->event->title}\"";
        $template = EmailTemplate::forKey('event_host_rsvp');

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('event_host_rsvp');

        if ($template?->is_active && $template->hasBodyOverride()) {
            return new Content(
                view: 'emails.template-override',
                with: ['html' => $template->body_override],
            );
        }

        return new Content(
            view: 'emails.event-host-rsvp',
            text: 'emails.event-host-rsvp-text',
            with: [
                'event' => $this->event,
                'rsvp' => $this->rsvp,
            ],
        );
    }
}
