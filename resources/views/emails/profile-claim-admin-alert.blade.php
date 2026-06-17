<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Profile Claimed — {{ $politician->full_name }} — {{ config('app.name', 'U9itus') }}</title>
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
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; word-break: break-all; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #6366f1; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; }
  .action-needed { background-color: rgba(99,102,241,0.08); border: 1px solid rgba(99,102,241,0.25); border-radius: 8px; padding: 12px 16px; font-size: 13px; color: #a5b4fc; margin: 20px 0; }
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
      <div class="tagline">Admin Alert</div>
    </div>

    <div class="body">
      <h1>🏛 Profile Claim Verified</h1>

      <p>
        A claimant has verified their email and completed the first step of claiming
        <strong style="color:#ffffff;">{{ $politician->full_name }}</strong>'s profile.
        Their new politician account is pending your review.
      </p>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Politician Profile</span>
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
        <div class="info-row">
          <span class="info-label">Claimant Email</span>
          <span class="info-value">{{ $claimantEmail }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Profile Slug</span>
          <span class="info-value">/p/{{ $politician->slug }}</span>
        </div>
      </div>

      <div class="action-needed">
        ⚡ <strong>Action required:</strong> Review the new account registration, confirm the claimant's identity matches the public record, then link their <code>user_id</code> to this politician profile.
      </div>

      <div class="btn-wrap">
        <a href="{{ $adminProfileUrl }}" class="btn">Review in Admin Panel →</a>
      </div>
    </div>

    <div class="footer">
      <p>{{ config('app.name', 'U9itus') }} &middot; Internal admin alert — do not forward.</p>
    </div>

  </div>
</div>
</body>
</html>
