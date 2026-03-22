{{ config('app.name', 'U9itus') }} - Refund Processed
===============================================

Hi {{ $user->first_name ?? $user->name }},

Your unused credit refund has been processed successfully.

  Refund Amount         : ${{ number_format($amount, 2) }}
  Credits Refunded      : {{ number_format($refundedCredits, 2) }}
  Updated Credit Balance: ${{ number_format($newBalance, 2) }}
@if($transactionId)
  Refund Transaction ID : {{ $transactionId }}
@endif
@if(!empty($reason))
  Reason                : {{ $reason }}
@endif
  Date                  : {{ now()->format('M j, Y g:i A') }}

View billing and invoices:
{{ route('politician.billing.invoices') }}

---
{{ config('app.url', 'https://u9itus.com') }}
Unsubscribe: {{ url('/unsubscribe') }}
