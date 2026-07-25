<?php

namespace App\Console\Commands;

use App\Enums\EventRsvpStatus;
use App\Mail\EventReminderMail;
use App\Models\CivicEvent;
use App\Models\EventReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendEventReminders extends Command
{
    protected $signature = 'events:send-reminders';

    protected $description = 'Send 24-hour and 1-hour reminder emails to event attendees';

    public function handle(): int
    {
        $now = now();

        foreach ([24, 1] as $hours) {
            $windowStart = $now->copy()->addHours($hours)->subMinutes(30);
            $windowEnd = $now->copy()->addHours($hours)->addMinutes(30);

            $events = CivicEvent::query()
                ->where('status', 'published')
                ->whereBetween('starts_at', [$windowStart, $windowEnd])
                ->where('starts_at', '>', $now)
                ->get();

            foreach ($events as $event) {
                $this->sendRemindersForEvent($event, $hours);
            }
        }

        return self::SUCCESS;
    }

    protected function sendRemindersForEvent(CivicEvent $event, int $hours): void
    {
        $attendingStatuses = [EventRsvpStatus::Yes->value, EventRsvpStatus::Approved->value, EventRsvpStatus::Waitlist->value];

        $rsvps = $event->rsvps()
            ->whereIn('status', $attendingStatuses)
            ->whereDoesntHave('reminders', fn ($q) => $q->where('hours_before', $hours))
            ->with('user')
            ->get();

        foreach ($rsvps as $rsvp) {
            $user = $rsvp->user;
            if (! $user || empty($user->email)) {
                continue;
            }

            try {
                Mail::to($user->email)->send(new EventReminderMail($event, $hours));
                EventReminder::create([
                    'civic_event_id' => $event->id,
                    'user_id' => $user->id,
                    'hours_before' => $hours,
                ]);
            } catch (\Throwable $e) {
                $this->error("Failed to send reminder to user {$user->id}: {$e->getMessage()}");
            }
        }
    }
}
