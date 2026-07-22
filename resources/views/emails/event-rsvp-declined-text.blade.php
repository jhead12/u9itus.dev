RSVP not approved

We're sorry — the host wasn't able to approve your RSVP for "{{ $event->title }}".

Event: {{ $event->title }}
Previously Scheduled: {{ $event->starts_at->setTimezone($event->timezone)->format('l, F j, Y g:i A') }} {{ $event->timezone }}

Browse other events:
{{ route('events.index') }}

If you have questions, reach out to the host through the event page.

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
