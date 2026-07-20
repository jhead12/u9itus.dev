You're confirmed! ✅

The host has approved your RSVP for "{{ $event->title }}". You're on the list.

Event: {{ $event->title }}
Date & Time: {{ $event->starts_at->setTimezone($event->timezone)->format('l, F j, Y g:i A') }} {{ $event->timezone }}
Location: {{ $event->is_virtual ? 'Virtual' : ($event->venue_name ? $event->venue_name . ', ' . $event->location_name : $event->location_name) }}
Your RSVP: {{ $rsvp->guest_count }} guest{{ $rsvp->guest_count === 1 ? '' : 's' }}
@if($event->is_virtual && $event->virtual_url)
Virtual URL: {{ $event->virtual_url }}
@endif

View event details:
{{ route('events.show', $event->slug) }}

Add the event to your calendar from the event page so you don't miss it.

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
