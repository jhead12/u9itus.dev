{{ config('app.name', 'U9itus') }} — Matching Campaign
=======================================================

You favorited "{{ $cause->title }}", and {{ $campaign->politician->full_name ?? 'a candidate' }} has a
campaign covering the same topic in your area.

SUMMARY
  Cause     : {{ $cause->title }}
  Campaign  : {{ $campaign->title }}
@if($campaign->politician)
  Candidate : {{ $campaign->politician->full_name }}
@endif

@if($campaign->politician?->slug)
View campaign:
{{ route('politician.public.show', $campaign->politician->slug) }}
@endif

You're receiving this because you favorited a related cause. Manage notification preferences in your account settings.
