✅ CAMPAIGN APPROVED — {{ config('app.name', 'U9itus') }}

Great news, {{ $campaign->politician->user->first_name ?? $campaign->politician->full_name ?? 'Politician' }}!

Your campaign has been reviewed and is now APPROVED AND ACTIVE.

Campaign: {{ $campaign->title }}
Status:   Active
@if($campaign->governance_level)
Level:    {{ ucfirst($campaign->governance_level) }}
@endif
@if($campaign->target_state)
State:    {{ strtoupper($campaign->target_state) }}
@endif
Revenue per view: ${{ number_format(config('u9itus.revenue_per_view', 0.60), 2) }}

Voters will begin receiving secure notification tokens to watch your video. You'll be
charged ${{ number_format(config('u9itus.revenue_per_view', 0.60), 2) }} per verified full view from your credit balance.

View campaign analytics:
{{ route('politician.campaigns.show', $campaign->id) }}

---
Questions? Reply to this email or visit your politician dashboard:
{{ route('politician.dashboard') }}

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url') }}
