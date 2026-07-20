You're off the waitlist!

A spot opened up for "{{ $event->title }}" and you've been promoted to attending.

Event: {{ $event->title }}
Date & Time: {{ $event->starts_at->setTimezone($event->timezone)->format('l, F j, Y g:i A') }} {{ $event->timezone }}
Location: {{ $event->is_virtual ? 'Virtual' : ($event->venue_name ? $event->venue_name . ', ' . $event->location_name : $event->location_name) }}
Your RSVP: {{ $rsvp->guest_count }} guest{{ $rsvp->guest_count === 1 ? '' : 's' }}
@if($event->is_virtual && $event->virtual_url)
Virtual URL: {{ $event->virtual_url }}
@endif

View event details:
{{ route('events.show', $event->slug) }}

If your plans change, please update your RSVP on the event page so someone else can attend.

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
