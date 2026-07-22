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

class EventWaitlistPromotionMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CivicEvent $event,
        public readonly EventRsvp $rsvp,
    ) {
    }

    public function envelope(): Envelope
    {
        $defaultSubject = "✅ You're off the waitlist: \"{$this->event->title}\"";
        $template = EmailTemplate::forKey('event_waitlist_promotion');

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('event_waitlist_promotion');

        if ($template?->is_active && $template->hasBodyOverride()) {
            return new Content(
                view: 'emails.template-override',
                with: ['html' => $template->body_override],
            );
        }

        return new Content(
            view: 'emails.event-waitlist-promotion',
            text: 'emails.event-waitlist-promotion-text',
            with: [
                'event' => $this->event,
                'rsvp' => $this->rsvp,
            ],
        );
    }
}
