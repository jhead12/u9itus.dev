Welcome to {{ config('app.name', 'U9itus') }}, {{ $user->first_name ?: $user->name }}!

Your {{ ucfirst($user->user_type ?? 'member') }} account has been created on U9itus — the Political Loyalty Ads Platform.

@if(($user->user_type ?? '') === 'politician')
You can now create your first campaign, set your governance level, and reach verified voters in your district.
Campaigns cost $1.00 per verified view and require approval before going live.
@elseif(($user->user_type ?? '') === 'voter')
Start watching approved political ads in your area and earn real money for every verified view.
Your identity is protected and payouts are processed securely.
@endif

Go to your dashboard: {{ url('/dashboard') }}

---
Need help? Visit {{ url('/contact') }}

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url') }}
