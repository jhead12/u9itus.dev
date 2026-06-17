<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Verify your profile claim — {{ config('app.name', 'U9itus') }}</title>
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
  .name-highlight { color: #fbbf24; font-weight: 600; }
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; word-break: break-all; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #f59e0b; color: #0f172a; font-size: 15px; font-weight: 700; text-decoration: none; border-radius: 10px; }
  .expiry-note { background-color: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #fbbf24; margin: 20px 0; }
  .link-fallback { word-break: break-all; font-size: 13px; color: #64748b; }
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
      <div class="tagline">Profile Claim Request</div>
    </div>

    <div class="body">
      <h1>🏛 Verify your claim request</h1>

      <p>
        We received a request to claim the public profile for
        <span class="name-highlight">{{ $politician->full_name }}</span> on U9itus.
        Click the button below to verify your email address and continue.
      </p>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Profile</span>
          <span class="info-value">{{ $politician->full_name }}</span>
        </div>
        @if($politician->political_office)
        <div class="info-row">
          <span class="info-label">Office</span>
          <span class="info-value">{{ $politician->political_office }}</span>
        </div>
        @endif
        @if($politician->state)
        <div class="info-row">
          <span class="info-label">State</span>
          <span class="info-value">{{ $politician->state }}</span>
        </div>
        @endif
      </div>

      <div class="btn-wrap">
        <a href="{{ $claimUrl }}" class="btn">Verify &amp; Claim Profile →</a>
      </div>

      <div class="expiry-note">
        ⏳ This link expires in 48 hours. If you did not request this, you can safely ignore this email — no account will be created.
      </div>

      <hr class="divider">

      <p style="font-size:13px;color:#64748b;">If the button above does not work, copy and paste this link into your browser:</p>
      <p class="link-fallback">{{ $claimUrl }}</p>
    </div>

    <div class="footer">
      <p>
        {{ config('app.name', 'U9itus') }} &middot;
        <a href="{{ url('/') }}">{{ parse_url(url('/'), PHP_URL_HOST) }}</a> &middot;
        You received this because someone submitted a profile claim request using this email address.
      </p>
    </div>

  </div>
</div>
</body>
</html>
