<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\CivicEventStatus;
use App\Enums\EventRsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CivicEventRequest;
use App\Mail\EventCancellationMail;
use App\Mail\EventRsvpApprovedMail;
use App\Mail\EventRsvpDeclinedMail;
use App\Models\CivicEvent;
use App\Models\EventRsvp;
use App\Models\PoliticianTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CivicEventController extends Controller
{
    public function index(): View
    {
        $host = $this->host();
        $events = $host->events()
            ->withCount('rsvps')
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('standalone.' . $this->role() . '.events.index', compact('events'));
    }

    public function create(): View
    {
        $topics = PoliticianTopic::orderBy('name')->get();
        return view('standalone.' . $this->role() . '.events.create', compact('topics'));
    }

    public function store(CivicEventRequest $request): RedirectResponse
    {
        $host = $this->host();
        $data = $this->validatedData($request);

        $event = $host->events()->create($data);
        $event->topics()->sync($request->input('topics', []));

        return redirect()
            ->route($this->role() . '.events.index')
            ->with('success', 'Event created successfully.');
    }

    public function edit(CivicEvent $event): View
    {
        $this->authorizeHost($event);

        $event->load('topics');
        $topics = PoliticianTopic::orderBy('name')->get();

        return view('standalone.' . $this->role() . '.events.edit', compact('event', 'topics'));
    }

    public function update(CivicEventRequest $request, CivicEvent $event): RedirectResponse
    {
        $this->authorizeHost($event);

        $event->update($request->validated());
        $event->topics()->sync($request->input('topics', []));

        return redirect()
            ->route($this->role() . '.events.index')
            ->with('success', 'Event updated successfully.');
    }

    public function rsvps(CivicEvent $event): View
    {
        $this->authorizeHost($event);

        $event->load([
            'rsvps' => fn ($q) => $q->with('user')->orderByRaw("FIELD(status, 'pending', 'yes', 'approved', 'waitlist', 'maybe', 'declined', 'no')")->orderByDesc('created_at'),
        ]);

        $pendingCount = $event->rsvps()->where('status', EventRsvpStatus::Pending)->count();
        $attendingCount = $event->rsvps()->whereIn('status', [EventRsvpStatus::Yes, EventRsvpStatus::Approved])->count();
        $waitlistCount = $event->rsvps()->where('status', EventRsvpStatus::Waitlist)->count();

        $role = $this->role();

        return view('standalone.' . $role . '.events.rsvps', compact(
            'event',
            'pendingCount',
            'attendingCount',
            'waitlistCount',
            'role'
        ));
    }

    public function approveRsvp(CivicEvent $event, EventRsvp $rsvp): RedirectResponse
    {
        $this->authorizeHost($event);
        abort_if($rsvp->civic_event_id !== $event->id, 403);

        if ($rsvp->status !== EventRsvpStatus::Pending) {
            return back()->with('error', 'Only pending RSVPs can be approved.');
        }

        $rsvp->update(['status' => EventRsvpStatus::Approved]);

        $user = $rsvp->user;
        if ($user && $user->email) {
            try {
                Mail::to($user->email)->send(new EventRsvpApprovedMail($event, $rsvp));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'RSVP approved.');
    }

    public function declineRsvp(CivicEvent $event, EventRsvp $rsvp): RedirectResponse
    {
        $this->authorizeHost($event);
        abort_if($rsvp->civic_event_id !== $event->id, 403);

        if ($rsvp->status !== EventRsvpStatus::Pending) {
            return back()->with('error', 'Only pending RSVPs can be declined.');
        }

        $rsvp->update(['status' => EventRsvpStatus::Declined]);

        $user = $rsvp->user;
        if ($user && $user->email) {
            try {
                Mail::to($user->email)->send(new EventRsvpDeclinedMail($event, $rsvp));
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return back()->with('success', 'RSVP declined.');
    }

    public function cancel(Request $request, CivicEvent $event): RedirectResponse
    {
        $this->authorizeHost($event);

        if ($event->status->value !== 'cancelled') {
            $event->update(['status' => CivicEventStatus::Cancelled]);
            $this->notifyAttendeesOfCancellation($event);
        }

        return back()->with('success', 'Event cancelled.');
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

    protected function validatedData(CivicEventRequest $request): array
    {
        return $request->validated() + [
            'uuid' => (string) Str::uuid(),
        ];
    }

    protected function authorizeHost(CivicEvent $event): void
    {
        $host = $this->host();

        abort_if(
            $event->host_type !== get_class($host) || $event->host_id !== $host->id,
            403,
            'Unauthorized action on this event.'
        );
    }

    protected function host(): mixed
    {
        $user = Auth::user();

        if ($this->role() === 'politician') {
            return $user?->politician ?? abort(403, 'Politician profile required.');
        }

        return $user?->citizen ?? abort(403, 'Citizen profile required.');
    }

    protected function role(): string
    {
        $user = Auth::user();

        if ($user?->hasRole('politician')) {
            return 'politician';
        }

        abort_if(! $user?->hasRole('citizen'), 403, 'Only citizens and politicians may manage civic events.');

        return 'citizen';
    }
}
