{{ config('app.name', 'U9itus') }} — Payout Processed
=====================================================

Hi {{ $user->first_name ?? $user->name }},

Great news — your payout has been processed and sent to your {{ $payoutMethod }} account.
Funds typically arrive within 1–3 business days.

  Amount Sent    : ${{ number_format($amount, 2) }}
  Videos Watched : {{ number_format($viewCount) }} views
  Avg. Per View  : ${{ $viewCount > 0 ? number_format($amount / $viewCount, 4) : '—' }}
  Method         : {{ $payoutMethod }}
@if($periodLabel)
  Period         : {{ $periodLabel }}
@endif
  Processed On   : {{ now()->format('M j, Y') }}

View your full earnings history:
{{ route('voter.earnings.history') }}

Keep watching campaigns to continue earning — new political messages are available regularly.

---
© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url', 'https://u9itus.com') }}
Unsubscribe: {{ url('/unsubscribe') }}
