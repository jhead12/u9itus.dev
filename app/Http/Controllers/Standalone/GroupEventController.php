<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\CivicEventStatus;
use App\Enums\EventRsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\GroupEventRequest;
use App\Mail\EventCancellationMail;
use App\Models\CivicEvent;
use App\Models\NeighborhoodGroup;
use App\Models\PoliticianTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Events hosted by a Neighborhood Group. Mirrors CivicEventController's
 * shape (same request class family, same "cancel not delete" convention
 * so RSVPs/history survive) but scoped to a specific $group via route
 * binding instead of "the authenticated citizen/politician's own host
 * record" — CivicEventController::host()/role() assume the latter and
 * don't take a route parameter, so this is a separate controller rather
 * than a third branch bolted onto that one.
 */
class GroupEventController extends Controller
{
    public function index(NeighborhoodGroup $group): View
    {
        $this->authorizeGroupAdmin($group);

        $events = $group->events()
            ->withCount('rsvps')
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('standalone.groups.events.index', compact('group', 'events'));
    }

    public function create(NeighborhoodGroup $group): View
    {
        $this->authorizeGroupAdmin($group);

        $topics = PoliticianTopic::orderBy('name')->get();

        return view('standalone.groups.events.create', compact('group', 'topics'));
    }

    public function store(GroupEventRequest $request, NeighborhoodGroup $group): RedirectResponse
    {
        $this->authorizeGroupAdmin($group);

        $event = $group->events()->create($request->validated());
        $event->topics()->sync($request->input('topics', []));

        return redirect()
            ->route('groups.events.index', $group)
            ->with('success', 'Event created successfully.');
    }

    public function edit(NeighborhoodGroup $group, CivicEvent $event): View
    {
        $this->authorizeGroupAdmin($group);
        $this->authorizeEventBelongsToGroup($group, $event);

        $event->load('topics');
        $topics = PoliticianTopic::orderBy('name')->get();

        return view('standalone.groups.events.edit', compact('group', 'event', 'topics'));
    }

    public function update(GroupEventRequest $request, NeighborhoodGroup $group, CivicEvent $event): RedirectResponse
    {
        $this->authorizeGroupAdmin($group);
        $this->authorizeEventBelongsToGroup($group, $event);

        $event->update($request->validated());
        $event->topics()->sync($request->input('topics', []));

        return redirect()
            ->route('groups.events.index', $group)
            ->with('success', 'Event updated successfully.');
    }

    /** Cancels rather than deletes, same as CivicEventController::cancel()
     *  — preserves RSVP history and notifies attendees who already RSVP'd. */
    public function cancel(NeighborhoodGroup $group, CivicEvent $event): RedirectResponse
    {
        $this->authorizeGroupAdmin($group);
        $this->authorizeEventBelongsToGroup($group, $event);

        if ($event->status->value !== 'cancelled') {
            $event->update(['status' => CivicEventStatus::Cancelled]);
            $this->notifyAttendeesOfCancellation($event);
        }

        return redirect()
            ->route('groups.events.index', $group)
            ->with('success', 'Event cancelled.');
    }

    protected function notifyAttendeesOfCancellation(CivicEvent $event): void
    {
        $notifiedStatuses = [EventRsvpStatus::Yes, EventRsvpStatus::Approved, EventRsvpStatus::Waitlist];

        $event->rsvps()
            ->whereIn('status', array_map(fn ($s) => $s->value, $notifiedStatuses))
            ->with('user')
            ->chunkById(100, function ($rsvps) use ($event): void {
                foreach ($rsvps as $rsvp) {
                    $user = $rsvp->user;
                    if (! $user || empty($user->email)) {
                        continue;
                    }

                    try {
                        Mail::to($user->email)->send(new EventCancellationMail($event, $user));
                    } catch (\Throwable $e) {
                        report($e);
                    }
                }
            });
    }

    private function authorizeGroupAdmin(NeighborhoodGroup $group): void
    {
        abort_unless($group->isAdmin(Auth::user()), 403, 'Only a group admin can manage this group\'s events.');
    }

    private function authorizeEventBelongsToGroup(NeighborhoodGroup $group, CivicEvent $event): void
    {
        abort_unless(
            $event->host_type === NeighborhoodGroup::class && (int) $event->host_id === (int) $group->id,
            404
        );
    }
}
