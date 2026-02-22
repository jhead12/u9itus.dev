<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Campaign Completed — {{ config('app.name', 'U9itus') }}</title>
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
  .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; background-color: rgba(148,163,184,0.12); color: #94a3b8; border: 1px solid rgba(148,163,184,0.25); margin-bottom: 20px; }
  .stats-grid { display: flex; gap: 12px; margin: 24px 0; }
  .stat-card { flex: 1; background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 16px; text-align: center; }
  .stat-card .stat-label { font-size: 11px; color: #64748b; letter-spacing: 1px; text-transform: uppercase; margin-bottom: 6px; }
  .stat-card .stat-value { font-size: 24px; font-weight: 700; color: #34d399; }
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #10b981; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; }
  .btn-secondary { display: inline-block; padding: 12px 24px; background-color: transparent; color: #94a3b8; font-size: 14px; font-weight: 500; text-decoration: none; border-radius: 10px; border: 1px solid #334155; margin-left: 12px; }
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
      <div class="status-badge">🏁 Campaign Completed</div>

      <h1>Your campaign has run its course!</h1>

      <p>
        <strong style="color:#e2e8f0;">{{ $campaign->title }}</strong> has completed its run —
        all allocated view credits have been used. Here's a summary of your campaign's performance.
      </p>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Views</div>
          <div class="stat-value">{{ number_format($totalViews) }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Spent</div>
          <div class="stat-value">${{ number_format($totalSpent, 0) }}</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Cost / View</div>
          <div class="stat-value">${{ $totalViews > 0 ? number_format($totalSpent / $totalViews, 2) : '—' }}</div>
        </div>
      </div>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Campaign</span>
          <span class="info-value">{{ $campaign->title }}</span>
        </div>
        @if($campaign->governance_level)
        <div class="info-row">
          <span class="info-label">Governance Level</span>
          <span class="info-value">{{ ucfirst($campaign->governance_level) }}</span>
        </div>
        @endif
        <div class="info-row">
          <span class="info-label">Total Verified Views</span>
          <span class="info-value" style="color:#34d399;">{{ number_format($totalViews) }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Total Campaign Spend</span>
          <span class="info-value">${{ number_format($totalSpent, 2) }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Completed On</span>
          <span class="info-value">{{ now()->format('M j, Y') }}</span>
        </div>
      </div>

      <div class="btn-wrap">
        <a href="{{ route('politician.analytics.campaign', $campaign->id) }}" class="btn">View Full Analytics →</a>
        <a href="{{ route('politician.campaigns.create') }}" class="btn-secondary">Launch New Campaign</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px">
        Ready to run another campaign?
        <a href="{{ route('politician.campaigns.create') }}" style="color:#34d399;text-decoration:none;">Create a new campaign</a>
        or
        <a href="{{ route('politician.billing') }}" style="color:#34d399;text-decoration:none;">add more credits</a>
        to your account.
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
