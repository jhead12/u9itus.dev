<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\CivicEventStatus;
use App\Http\Controllers\Controller;
use App\Models\CivicEvent;
use App\Models\PoliticianTopic;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class PublicCivicEventController extends Controller
{
    public function index(Request $request): View
    {
        $query = CivicEvent::query()
            ->where('status', CivicEventStatus::Published)
            ->where('starts_at', '>=', now()->subDay())
            ->with('host.user')
            ->orderBy('starts_at');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(fn (Builder $sq) => $sq
                ->where('title', 'like', "%{$q}%")
                ->orWhere('description', 'like', "%{$q}%")
                ->orWhere('location_name', 'like', "%{$q}%")
            );
        }

        if ($request->filled('location')) {
            $loc = $request->input('location');
            $query->where(fn (Builder $sq) => $sq
                ->where('location_name', 'like', "%{$loc}%")
                ->orWhere('city', 'like', "%{$loc}%")
                ->orWhere('state', 'like', "%{$loc}%")
            );
        }

        if ($request->filled('topic')) {
            $topicSlug = $request->input('topic');
            $query->whereHas('topics', fn (Builder $sq) => $sq->where('slug', $topicSlug));
        }

        $events = $query->paginate(12)->withQueryString();
        $topics = PoliticianTopic::orderBy('name')->get();

        return view('standalone.public.events.index', compact('events', 'topics'));
    }

    public function show(CivicEvent $event): View
    {
        abort_if($event->status->value !== CivicEventStatus::Published->value, 404);

        $event->load(['host.user', 'topics']);

        $rsvp = null;
        if (auth()->check()) {
            $rsvp = $event->rsvps()->where('user_id', auth()->id())->first();
        }

        return view('standalone.public.events.show', compact('event', 'rsvp'));
    }

    public function ics(CivicEvent $event): Response
    {
        abort_if($event->status->value !== CivicEventStatus::Published->value, 404);

        $uid = $event->uuid;
        $dtStamp = now()->utc()->format('Ymd\\THis\\Z');
        $dtStart = $event->starts_at->utc()->format('Ymd\\THis\\Z');
        $dtEnd = $event->ends_at->utc()->format('Ymd\\THis\\Z');
        $summary = $this->escapeIcs($event->title);
        $description = $this->escapeIcs(strip_tags($event->description));
        $location = $this->escapeIcs($event->is_virtual ? ($event->virtual_url ?? 'Virtual') : ($event->venue_name ?? $event->location_name));
        $url = route('events.show', $event->slug);

        $ics = <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//U9itus//CivicEvent//EN
BEGIN:VEVENT
UID:{$uid}@u9itus.dev
DTSTAMP:{$dtStamp}
DTSTART:{$dtStart}
DTEND:{$dtEnd}
SUMMARY:{$summary}
DESCRIPTION:{$description}
LOCATION:{$location}
URL:{$url}
END:VEVENT
END:VCALENDAR
ICS;

        return response(trim($ics), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $event->slug . '.ics"',
        ]);
    }

    protected function escapeIcs(string $text): string
    {
        return str_replace(["\\", ";", ",", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n'], $text);
    }
}
