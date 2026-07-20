Event Cancelled

We're sorry — "{{ $event->title }}" has been cancelled by the host.

Event: {{ $event->title }}
Previously Scheduled: {{ $event->starts_at->setTimezone($event->timezone)->format('l, F j, Y g:i A') }} {{ $event->timezone }}
Location: {{ $event->is_virtual ? 'Virtual' : ($event->venue_name ? $event->venue_name . ', ' . $event->location_name : $event->location_name) }}

Explore other events:
{{ url('/') }}

If you paid to attend or made a donation related to this event, the host will contact you separately about refunds.

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
