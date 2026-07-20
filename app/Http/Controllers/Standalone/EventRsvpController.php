<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\EventRsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\EventRsvpRequest;
use App\Models\CivicEvent;
use Illuminate\Http\RedirectResponse;

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
        $event->rsvps()->updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'status' => $status,
                'guest_count' => $guestCount,
                'notes' => $notes,
            ]
        );
    }
}
