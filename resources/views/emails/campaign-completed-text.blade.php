{{ config('app.name', 'U9itus') }} — Campaign Completed
======================================================

Your campaign "{{ $campaign->title }}" has completed its run —
all allocated view credits have been used.

CAMPAIGN SUMMARY
  Campaign        : {{ $campaign->title }}
  Total Views     : {{ number_format($totalViews) }}
  Total Spent     : ${{ number_format($totalSpent, 2) }}
  Cost Per View   : ${{ $totalViews > 0 ? number_format($totalSpent / $totalViews, 4) : '—' }}
  Completed On    : {{ now()->format('M j, Y') }}

View full analytics:
{{ route('politician.analytics.campaign', $campaign->id) }}

Launch a new campaign:
{{ route('politician.campaigns.create') }}

Add more credits:
{{ route('politician.billing') }}

---
© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url', 'https://u9itus.com') }}
Unsubscribe: {{ url('/unsubscribe') }}
