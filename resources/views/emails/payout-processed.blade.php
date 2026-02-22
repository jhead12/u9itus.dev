<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Payout Processed — {{ config('app.name', 'U9itus') }}</title>
<style>
  body { margin: 0; padding: 0; background-color: #0f172a; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #e2e8f0; }
  .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px; }
  .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; overflow: hidden; }
  .header { background-color: #0f172a; padding: 32px 40px 24px; border-bottom: 1px solid #334155; text-align: center; }
  .logo { font-size: 28px; font-weight: 300; letter-spacing: -0.5px; color: #ffffff; }
  .logo span { color: #34d399; font-weight: 700; }
  .tagline { margin-top: 6px; font-size: 12px; color: #64748b; letter-spacing: 1px; text-transform: uppercase; }
  .body { padding: 36px 40px; }
  h1 { margin: 0 0 12px; font-size: 22px; font-weight: 600; color: #ffffff; }
  p { margin: 0 0 16px; font-size: 15px; line-height: 1.65; color: #94a3b8; }
  .highlight { color: #34d399; font-weight: 500; }
  .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; background-color: rgba(52,211,153,0.15); color: #34d399; border: 1px solid rgba(52,211,153,0.35); margin-bottom: 20px; }
  .amount-hero { text-align: center; margin: 24px 0; padding: 24px; background: linear-gradient(135deg, rgba(52,211,153,0.08) 0%, rgba(16,185,129,0.04) 100%); border: 1px solid rgba(52,211,153,0.2); border-radius: 14px; }
  .amount-hero .label { font-size: 12px; color: #64748b; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 6px; }
  .amount-hero .value { font-size: 44px; font-weight: 700; color: #34d399; margin: 0; }
  .amount-hero .sub { font-size: 14px; color: #64748b; margin-top: 4px; }
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #10b981; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; }
  .divider { border: none; border-top: 1px solid #334155; margin: 28px 0; }
  .footer { padding: 20px 40px; border-top: 1px solid #334155; text-align: center; }
  .footer p { font-size: 12px; color: #475569; margin: 0; }
  .footer a { color: #64748b; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">

    <div class="header">
      <div class="logo"><span>U9</span>itus</div>
      <div class="tagline">Political Loyalty Ads Platform</div>
    </div>

    <div class="body">
      <div class="status-badge">💰 Payout Sent</div>

      <h1>Your earnings are on the way, {{ $user->first_name ?? $user->name }}!</h1>

      <p>
        Great news — your payout has been <span class="highlight">processed and sent</span>
        to your {{ $payoutMethod }} account. Funds typically arrive within 1–3 business days.
      </p>

      <div class="amount-hero">
        <div class="label">Amount Sent</div>
        <div class="value">${{ number_format($amount, 2) }}</div>
        <div class="sub">via {{ $payoutMethod }}{{ $periodLabel ? ' · ' . $periodLabel : '' }}</div>
      </div>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Payout Amount</span>
          <span class="info-value" style="color:#34d399;">${{ number_format($amount, 2) }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Videos Watched</span>
          <span class="info-value">{{ number_format($viewCount) }} views</span>
        </div>
        <div class="info-row">
          <span class="info-label">Avg. Per View</span>
          <span class="info-value">${{ $viewCount > 0 ? number_format($amount / $viewCount, 4) : '—' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Payment Method</span>
          <span class="info-value">{{ $payoutMethod }}</span>
        </div>
        @if($periodLabel)
        <div class="info-row">
          <span class="info-label">Period</span>
          <span class="info-value">{{ $periodLabel }}</span>
        </div>
        @endif
        <div class="info-row">
          <span class="info-label">Processed On</span>
          <span class="info-value">{{ now()->format('M j, Y') }}</span>
        </div>
      </div>

      <div class="btn-wrap">
        <a href="{{ route('voter.earnings.history') }}" class="btn">View Earnings History →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px">
        Keep watching campaigns to continue earning. New political messages are available regularly.
      </p>
    </div>

    <div class="footer">
      <p>
        © {{ date('Y') }} {{ config('app.name', 'U9itus') }} · Political Loyalty Ads Platform<br />
        <a href="{{ url('/') }}">{{ parse_url(config('app.url', 'https://u9itus.com'), PHP_URL_HOST) }}</a>
        &nbsp;·&nbsp;
        <a href="{{ url('/unsubscribe') }}">Unsubscribe</a>
      </p>
    </div>

  </div>
</div>
</body>
</html>
