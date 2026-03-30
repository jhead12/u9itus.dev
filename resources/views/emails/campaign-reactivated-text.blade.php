Campaign Reactivated - {{ config('app.name', 'U9itus') }}

Your campaign "{{ $campaign->title }}" was reactivated by an administrator and is now active again.

View campaign:
{{ route('politician.campaigns.show', $campaign->id) }}

---
{{ config('app.name', 'U9itus') }}
