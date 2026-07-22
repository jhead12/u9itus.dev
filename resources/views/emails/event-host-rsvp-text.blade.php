New RSVP — {{ config('app.name', 'U9itus') }}

{{ $rsvp->user->name }} responded to your event "{{ $event->title }}".

Response: {{ $rsvp->status->label() }}
Guests: {{ $rsvp->guest_count }}
@if($rsvp->notes)
Note: {{ $rsvp->notes }}
@endif
Total attending: {{ $event->attendingCount() }}
@if($event->capacity)
Capacity: {{ $event->capacity }}
@endif

Manage event: {{ route('events.show', $event->slug) }}
