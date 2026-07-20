<?php

namespace App\Mail;

use App\Models\CivicEvent;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EventReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CivicEvent $event,
        public readonly int $hoursUntilStart,
    ) {
    }

    public function envelope(): Envelope
    {
        $defaultSubject = match ($this->hoursUntilStart) {
            1 => "⏰ Your event starts in 1 hour — {$this->event->title}",
            default => "📅 Reminder: {$this->event->title} is in {$this->hoursUntilStart} hours",
        };
        $template = EmailTemplate::forKey('event_reminder_attendee');

        return new Envelope(
            subject: $template?->is_active ? $template->effectiveSubject($defaultSubject) : $defaultSubject,
        );
    }

    public function content(): Content
    {
        $template = EmailTemplate::forKey('event_reminder_attendee');

        if ($template?->is_active && $template->hasBodyOverride()) {
            return new Content(
                view: 'emails.template-override',
                with: ['html' => $template->body_override],
            );
        }

        return new Content(
            view: 'emails.event-reminder',
            text: 'emails.event-reminder-text',
            with: [
                'event' => $this->event,
                'hoursUntilStart' => $this->hoursUntilStart,
            ],
        );
    }
}
