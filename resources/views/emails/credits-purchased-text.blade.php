{{ config('app.name', 'U9itus') }} — Credits Added
=================================================

Hi {{ $user->first_name ?? $user->name }},

Your payment was processed successfully. Your campaign view credits
have been added to your balance and are ready to use.

  Credits Purchased : {{ number_format($credits) }} credits
  Amount Charged    : ${{ number_format($amount, 2) }}
  Cost Per Credit   : ${{ $credits > 0 ? number_format($amount / $credits, 4) : '—' }}
  New Balance       : ${{ number_format($newBalance, 2) }}
@if($transactionId)
  Transaction ID    : {{ $transactionId }}
@endif
  Date              : {{ now()->format('M j, Y g:i A') }}

Launch a campaign:
{{ route('politician.campaigns.index') }}

View billing & invoices:
{{ route('politician.billing.invoices') }}

---
© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url', 'https://u9itus.com') }}
Unsubscribe: {{ url('/unsubscribe') }}
