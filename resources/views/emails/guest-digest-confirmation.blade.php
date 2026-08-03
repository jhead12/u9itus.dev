<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Confirm your updates — {{ config('app.name', 'U9itus') }}</title>
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
      <h1>Confirm your saved-places updates</h1>

      <p>
        Hi{{ $voter->full_name && $voter->full_name !== 'Guest Subscriber' ? ' ' . $voter->full_name : '' }}, you
        (or someone using this email address) asked for weekly updates about the districts and
        cities you've saved on the U9itus map — new candidate news, endorsements, and other
        activity for those places.
      </p>

      <p>
        Click below to confirm. If you didn't request this, you can safely ignore this email —
        no updates will be sent unless you confirm.
      </p>

      <div class="btn-wrap">
        <a href="{{ $confirmUrl }}" class="btn">Confirm My Updates →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px;color:#64748b;">
        This link expires in 3 days. Questions? Reply to this email or visit
        <a href="{{ config('app.url') }}" style="color:#7dd3fc;">{{ config('app.url') }}</a>.
      </p>
    </div>

    <div class="footer">
      <p>{{ config('app.name', 'U9itus') }} · Political Loyalty Ads Platform</p>
      <p style="margin-top:6px;">
        <a href="{{ config('app.url') }}">{{ config('app.url') }}</a>
      </p>
    </div>

  </div>
</div>
</body>
</html>
