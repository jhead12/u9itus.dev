{{ config('app.name', 'U9itus') }} — Low Credit Balance Warning
================================================================

Hi {{ $user->first_name ?? $user->name }},

Your campaign credit balance has fallen below the recommended threshold.
Add funds now to keep your campaigns running without interruption.

  Current Balance   : ${{ number_format($currentBalance, 2) }}
  Remaining Views   : ≈ {{ number_format($remainingViews) }} views
@if($campaignTitle)
  Affected Campaign : {{ $campaignTitle }}
@endif

When your balance reaches zero, your active campaigns will be paused automatically.

Add credits now:
{{ route('politician.billing') }}

You can also enable automatic credit reloading in your billing settings
to prevent future interruptions.

---
© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url', 'https://u9itus.com') }}
Unsubscribe: {{ url('/unsubscribe') }}
