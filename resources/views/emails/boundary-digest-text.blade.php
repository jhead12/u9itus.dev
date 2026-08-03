Your saved places — {{ config('app.name', 'U9itus') }}
{{ $periodLabel }}

Hi {{ $voter->full_name ?: 'there' }},
@foreach ($sections as $section)

{{ $section['boundary']->label }}
@foreach ($section['candidates'] as $row)
- {{ $row['politician']->full_name }}
@foreach ($row['endorsements'] as $endorsement)
  * Endorsement: {{ $endorsement->label }}
@endforeach
@foreach ($row['news'] as $article)
  * {{ $article->headline }} — {{ $article->source_url }}
@endforeach
@endforeach
@endforeach

View the map: {{ url('/map') }}

---
You're receiving this because you opted into weekly saved-places updates.
Manage this anytime from your notification settings.

{{ config('app.name', 'U9itus') }}
