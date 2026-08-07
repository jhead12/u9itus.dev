@php
    // {{ }} always HTML-escapes — needed for the HTML sibling view, wrong here
    // since this is the plain-text alternative. Decode entities back for names/
    // titles that can contain apostrophes, ampersands, etc.
    $plain = fn ($value) => html_entity_decode((string) $value, ENT_QUOTES);
@endphp
Your saved places — {{ config('app.name', 'U9itus') }}
{{ $periodLabel }}

Hi {!! $plain($voter->full_name ?: 'there') !!},
@foreach ($sections as $section)

{!! $plain($section['boundary']->label) !!}
@foreach ($section['candidates'] as $row)
- {!! $plain($row['politician']->full_name) !!}
@foreach ($row['endorsements'] as $endorsement)
  * Endorsement: {!! $plain($endorsement->label) !!}
@endforeach
@foreach ($row['videos'] as $video)
  * Video: {!! $plain($video->title) !!} — {{ $video->url }}{{ $video->view_count ? ' (' . number_format($video->view_count) . ' views)' : '' }}
@endforeach
@foreach ($row['news'] as $article)
  * News: {!! $plain($article->headline) !!}{{ $article->published_at ? ' (' . $article->published_at->format('M j') . ')' : '' }} — {{ $article->source_url }}
@endforeach
@endforeach
@endforeach
@if($remainingCount > 0)

+{{ $remainingCount }} more of your saved {{ Str::plural('place', $remainingCount) }} had updates too — view them on the map: {{ url('/map') }}
@endif

View the map: {{ url('/map') }}

---
You're receiving this because you opted into saved-places updates.
@if($unsubscribeUrl)
Unsubscribe: {{ $unsubscribeUrl }}
@else
Manage this anytime from your notification settings.
@endif

{{ config('app.name', 'U9itus') }}
