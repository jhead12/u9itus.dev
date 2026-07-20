<?php

namespace App\Mail;

use App\Models\CivicEvent;
use App\Models\User;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventCancellationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CivicEvent $event,
        public readonly User $user,
    ) {
    }

    public function envelope(): Envelope
    {
        $defaultSubject = "❌ Cancelled: \"{$this->event->title}\"";
        $template = EmailTemplate::forKey('event_cancellation_attendee');

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('event_cancellation_attendee');

        if ($template?->is_active && $template->hasBodyOverride()) {
            return new Content(
                view: 'emails.template-override',
                with: ['html' => $template->body_override],
            );
        }

        return new Content(
            view: 'emails.event-cancellation',
            text: 'emails.event-cancellation-text',
            with: [
                'event' => $this->event,
                'user' => $this->user,
            ],
        );
    }
}
