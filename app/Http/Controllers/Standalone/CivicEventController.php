<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\CivicEventStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CivicEventRequest;
use App\Models\CivicEvent;
use App\Models\PoliticianTopic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

    public function cancel(Request $request, CivicEvent $event): RedirectResponse
    {
        $this->authorizeHost($event);

        if ($event->status->value !== 'cancelled') {
            $event->update(['status' => CivicEventStatus::Cancelled]);
        }

        return back()->with('success', 'Event cancelled.');
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
