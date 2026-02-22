<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Low Credit Balance — {{ config('app.name', 'U9itus') }}</title>
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
  .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; background-color: rgba(245,158,11,0.12); color: #f59e0b; border: 1px solid rgba(245,158,11,0.30); margin-bottom: 20px; }
  .alert-box { background-color: rgba(245,158,11,0.07); border: 1px solid rgba(245,158,11,0.25); border-radius: 10px; padding: 20px 24px; margin: 20px 0; text-align: center; }
  .alert-box .balance-label { font-size: 12px; color: #92400e; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 6px; }
  .alert-box .balance-value { font-size: 36px; font-weight: 700; color: #f59e0b; }
  .alert-box .balance-sub { font-size: 13px; color: #78350f; margin-top: 4px; }
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #f59e0b; color: #1c1917; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; }
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
      <div class="status-badge">⚠️ Low Balance</div>

      <h1>Your credits are running low, {{ $user->first_name ?? $user->name }}.</h1>

      <p>
        Your campaign credit balance has fallen below the recommended threshold.
        Add funds now to keep your campaigns running without interruption.
      </p>

      <div class="alert-box">
        <div class="balance-label">Current Balance</div>
        <div class="balance-value">${{ number_format($currentBalance, 2) }}</div>
        <div class="balance-sub">≈ {{ number_format($remainingViews) }} views remaining</div>
      </div>

      @if($campaignTitle)
      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Affected Campaign</span>
          <span class="info-value">{{ $campaignTitle }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Remaining Views</span>
          <span class="info-value" style="color:#f59e0b;">{{ number_format($remainingViews) }}</span>
        </div>
      </div>
      @endif

      <p style="font-size:14px; color:#64748b;">
        When your balance reaches zero, your active campaigns will be paused automatically.
        Top up now to keep reaching voters without any downtime.
      </p>

      <div class="btn-wrap">
        <a href="{{ route('politician.billing') }}" class="btn">Add Credits Now →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px">
        <strong style="color:#cbd5e1">Auto-reload tip:</strong><br />
        You can enable automatic credit reloading in your
        <a href="{{ route('politician.billing') }}" style="color:#34d399;text-decoration:none;">billing settings</a>
        to prevent interruptions.
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
