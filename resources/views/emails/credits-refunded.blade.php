<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Refund Processed - {{ config('app.name', 'U9itus') }}</title>
<style>
  body { margin: 0; padding: 0; background-color: #0f172a; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #e2e8f0; }
  .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px; }
  .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; overflow: hidden; }
  .header { background-color: #0f172a; padding: 32px 40px 24px; border-bottom: 1px solid #334155; text-align: center; }
  .title { margin: 0; font-size: 22px; color: #34d399; font-weight: 700; }
  .body { padding: 28px 40px; font-size: 15px; line-height: 1.6; color: #cbd5e1; }
  .info { margin: 20px 0; border: 1px solid #334155; border-radius: 10px; overflow: hidden; }
  .info-row { display: flex; justify-content: space-between; padding: 10px 14px; border-bottom: 1px solid #334155; }
  .info-row:last-child { border-bottom: none; }
  .info-label { color: #94a3b8; }
  .info-value { color: #f8fafc; font-weight: 600; }
  .footer { padding: 18px 40px 28px; font-size: 12px; color: #94a3b8; text-align: center; }
  .footer a { color: #64748b; text-decoration: none; }
</style>
</head>
<body>
  <div class="wrapper">
    <div class="card">
      <div class="header">
        <h1 class="title">Refund Processed</h1>
      </div>

      <div class="body">
        <p>Hi {{ $user->first_name ?? $user->name }},</p>

        <p>
          Your unused credit refund has been processed successfully.
        </p>

        <div class="info">
          <div class="info-row">
            <span class="info-label">Refund Amount</span>
            <span class="info-value">${{ number_format($amount, 2) }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Credits Refunded</span>
            <span class="info-value">{{ number_format($refundedCredits, 2) }}</span>
          </div>
          <div class="info-row">
            <span class="info-label">Updated Credit Balance</span>
            <span class="info-value">${{ number_format($newBalance, 2) }}</span>
          </div>
          @if($transactionId)
          <div class="info-row">
            <span class="info-label">Refund Transaction ID</span>
            <span class="info-value">{{ $transactionId }}</span>
          </div>
          @endif
          @if(!empty($reason))
          <div class="info-row">
            <span class="info-label">Reason</span>
            <span class="info-value">{{ $reason }}</span>
          </div>
          @endif
          <div class="info-row">
            <span class="info-label">Date</span>
            <span class="info-value">{{ now()->format('M j, Y g:i A') }}</span>
          </div>
        </div>

        <p>
          You can review the transaction in your
          @if($user->hasRole('citizen') && ! $user->hasRole('politician'))
              <a href="{{ route('citizen.billing.invoices') }}" style="color:#34d399;text-decoration:none;">Billing &amp; Invoices</a>
          @else
              <a href="{{ route('politician.billing.invoices') }}" style="color:#34d399;text-decoration:none;">Billing &amp; Invoices</a>
          @endif
          history.
        </p>
      </div>

      <div class="footer">
        <p>
          &copy; {{ date('Y') }} {{ config('app.name', 'U9itus') }}<br />
          <a href="{{ url('/') }}">{{ parse_url(config('app.url', 'https://u9itus.com'), PHP_URL_HOST) }}</a>
        </p>
      </div>
    </div>
  </div>
</body>
</html>
