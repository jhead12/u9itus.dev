Event Reminder — {{ config('app.name', 'U9itus') }}

"{{ $event->title }}" starts in {{ $hoursUntilStart }} hour{{ $hoursUntilStart === 1 ? '' : 's' }}.

@php
$startsAt = $event->starts_at->setTimezone($event->timezone);
@endphp

Date & Time: {{ $startsAt->format('l, F j, Y g:i A') }} {{ $event->timezone }}
Location: {{ $event->is_virtual ? 'Virtual' : ($event->venue_name ? $event->venue_name . ', ' . $event->location_name : $event->location_name) }}
@if($event->is_virtual && $event->virtual_url)
Virtual URL: {{ $event->virtual_url }}
@endif

View event details: {{ route('events.show', $event->slug) }}
