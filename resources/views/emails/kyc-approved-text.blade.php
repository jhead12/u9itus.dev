{{ config('app.name', 'U9itus') }} — Identity Verified
==============================================

Hi {{ $user->first_name ?? $user->name }},

Your identity has been reviewed and OFFICIALLY VERIFIED by our admin team.
Your account now has full access to the platform.

Details:
  Name:        {{ $user->name }}
  Email:       {{ $user->email }}
  Account:     {{ ucfirst($user->user_type ?? 'Member') }}
  Verified On: {{ now()->format('M j, Y') }}
  Status:      Approved ✅

@if(($user->user_type ?? '') === 'politician')
Go to your politician dashboard:
{{ route('politician.dashboard') }}
@elseif(($user->user_type ?? '') === 'voter')
Start earning — go to your voter dashboard:
{{ route('voter.dashboard') }}
@else
Go to your dashboard:
{{ route('dashboard') }}
@endif

Questions? Reply to this email or contact {{ config('mail.from.address') }}.

---
© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url', 'https://u9itus.com') }}
Unsubscribe: {{ url('/unsubscribe') }}
