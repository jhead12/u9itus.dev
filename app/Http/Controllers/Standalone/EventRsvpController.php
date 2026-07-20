<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\EventRsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventRsvpRequest;
use App\Mail\EventHostRsvpMail;
use App\Mail\EventWaitlistPromotionMail;
use App\Models\CivicEvent;
use App\Models\EventRsvp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class EventRsvpController extends Controller
{
    public function store(EventRsvpRequest $request, CivicEvent $event): RedirectResponse
    {
        if ($event->status->value === 'cancelled' || $event->starts_at->isPast()) {
            return back()->with('error', 'RSVPs are closed for this event.');
        }

        $validated = $request->validated();
        $status = EventRsvpStatus::from($validated['status']);
        $guestCount = (int) $validated['guest_count'];

        // A "no" response should not consume capacity or waitlist.
        if ($status === EventRsvpStatus::No) {
            $this->saveRsvp($event, $status, $guestCount, $validated['notes'] ?? null);
            return back()->with('success', 'Your RSVP has been updated.');
        }

        $existing = $event->rsvps()->where('user_id', auth()->id())->first();

        // Determine whether this user should attend or waitlist.
        $targetStatus = $this->resolveAttendingStatus($event, $status, $guestCount, $existing);

        $this->saveRsvp($event, $targetStatus, $guestCount, $validated['notes'] ?? null);

        $message = match ($targetStatus) {
            EventRsvpStatus::Waitlist => 'You have been added to the waitlist.',
            EventRsvpStatus::Pending => 'Your RSVP is pending host approval.',
            default => 'Your RSVP has been saved.',
        };

        return back()->with('success', $message);
    }

    protected function resolveAttendingStatus(
        CivicEvent $event,
        EventRsvpStatus $status,
        int $guestCount,
        ?\App\Models\EventRsvp $existing
    ): EventRsvpStatus {
        if ($event->rsvp_requires_approval) {
            return EventRsvpStatus::Pending;
        }

        if (! $event->capacity) {
            return EventRsvpStatus::Yes;
        }

        $currentAttending = $event->attendingCount();

        // If user already has a confirmed RSVP, temporarily subtract their prior guests so an update does not push them out.
        if ($existing?->isAttending()) {
            $currentAttending -= $existing->guest_count;
        }

        if (($currentAttending + $guestCount) <= $event->capacity) {
            return EventRsvpStatus::Yes;
        }

        return EventRsvpStatus::Waitlist;
    }

    protected function saveRsvp(CivicEvent $event, EventRsvpStatus $status, int $guestCount, ?string $notes): void
    {
        $priorStatus = $event->rsvps()->where('user_id', auth()->id())->value('status');

        $rsvp = $event->rsvps()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'status' => $status,
                'guest_count' => $guestCount,
                'notes' => $notes,
            ]
        );

        // Notify the host for any non-no RSVP (new or updated).
        if ($status !== EventRsvpStatus::No) {
            $host = $event->host;
            $hostEmail = $host?->receipt_email ?: $host?->user?->email;
            if ($hostEmail) {
                Mail::to($hostEmail)->send(new EventHostRsvpMail($event, $rsvp));
            }
        }

        // When a confirmed attendee drops out, promote waitlisted users FIFO.
        $wasAttending = $priorStatus && in_array($priorStatus->value, [EventRsvpStatus::Yes->value, EventRsvpStatus::Approved->value], true);
        if ($wasAttending && $status === EventRsvpStatus::No) {
            $this->promoteWaitlist($event);
        }
    }

    protected function promoteWaitlist(CivicEvent $event): void
    {
        if (! $event->capacity) {
            return;
        }

        $available = $event->capacity - $event->fresh()->attendingCount();
        if ($available <= 0) {
            return;
        }

        $waitlisted = $event->rsvps()
            ->where('status', EventRsvpStatus::Waitlist)
            ->where('guest_count', '<=', $available)
            ->orderBy('created_at')
            ->orderBy('id')
            ->with('user')
            ->get();

        foreach ($waitlisted as $rsvp) {
            $attending = $event->fresh()->attendingCount();
            $available = $event->capacity - $attending;

            if ($rsvp->guest_count > $available) {
                continue;
            }

            $rsvp->update(['status' => EventRsvpStatus::Yes]);

            $user = $rsvp->user;
            if ($user && $user->email) {
                try {
                    Mail::to($user->email)->send(new EventWaitlistPromotionMail($event, $rsvp));
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        }
    }
}
