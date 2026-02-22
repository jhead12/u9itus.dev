<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Identity Verification — Action Required</title>
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
  .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; background-color: rgba(248,113,113,0.12); color: #f87171; border: 1px solid rgba(248,113,113,0.30); margin-bottom: 20px; }
  .reason-box { background-color: rgba(248,113,113,0.07); border: 1px solid rgba(248,113,113,0.25); border-radius: 10px; padding: 16px 20px; margin: 20px 0; font-size: 14px; color: #fca5a5; line-height: 1.6; }
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; }
  .step-list { list-style: none; padding: 0; margin: 16px 0; }
  .step-list li { padding: 8px 0; border-bottom: 1px solid #1e293b; font-size: 14px; color: #94a3b8; }
  .step-list li:last-child { border-bottom: none; }
  .step-list li::before { content: "→ "; color: #f59e0b; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #3b82f6; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; }
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
      <div class="status-badge">⚠️ Verification Unsuccessful</div>

      <h1>Action required, {{ $user->first_name ?? $user->name }}.</h1>

      <p>
        Unfortunately, your identity verification submission could not be approved at this time.
        Please review the reason below and resubmit with the corrected documentation.
      </p>

      <div class="reason-box">
        <strong style="color:#f87171;">Reason:</strong><br />
        {{ $reason }}
      </div>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Account</span>
          <span class="info-value">{{ $user->email }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Reviewed On</span>
          <span class="info-value">{{ now()->format('M j, Y') }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">KYC Status</span>
          <span class="info-value" style="color:#f87171;">Rejected</span>
        </div>
      </div>

      <p><strong style="color:#e2e8f0;">To resubmit, please ensure:</strong></p>
      <ul class="step-list">
        <li>Your government-issued ID is clear, fully visible, and not expired</li>
        <li>The document matches the name on your account</li>
        <li>Photos are not blurry, cropped, or obscured</li>
        <li>All four corners of the document are visible</li>
      </ul>

      <div class="btn-wrap">
        @if(($user->user_type ?? '') === 'politician')
        <a href="{{ route('politician.profile') }}" class="btn">Resubmit Identity Documents →</a>
        @else
        <a href="{{ route('voter.profile') }}" class="btn">Resubmit Identity Documents →</a>
        @endif
      </div>

      <hr class="divider" />

      <p style="font-size:13px">
        <strong style="color:#cbd5e1">Need help?</strong><br />
        Reply to this email and our support team will assist you. We're here to get you verified.
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
