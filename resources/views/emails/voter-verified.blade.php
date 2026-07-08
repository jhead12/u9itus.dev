<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Identity Verified — {{ config('app.name', 'U9itus') }}</title>
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
  .benefit-list { list-style: none; padding: 0; margin: 0 0 20px; }
  .benefit-list li { padding: 10px 0; border-bottom: 1px solid #334155; font-size: 14px; color: #cbd5e1; display: flex; align-items: flex-start; gap: 10px; }
  .benefit-list li:last-child { border-bottom: none; }
  .benefit-list li .icon { color: #34d399; font-size: 16px; flex-shrink: 0; }
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
      <div class="status-badge">✅ Identity Verified</div>

      <h1>You're verified, {{ $voter->full_name ?: 'voter' }}!</h1>

      <p>
        Your identity verification through <span class="highlight">Authentic User Verifier</span>
        (powered by Stripe Connect) is now complete. Your account is fully active and you can
        start earning from political ad views right away.
      </p>

      <ul class="benefit-list">
        <li>
          <span class="icon">💰</span>
          <span>Earn <strong class="highlight">$0.50 per completed ad view</strong> — payouts deposited to your connected account weekly.</span>
        </li>
        <li>
          <span class="icon">🗳️</span>
          <span>Watch political messages from candidates in your district and make your voice count.</span>
        </li>
        <li>
          <span class="icon">🔗</span>
          <span>Share your referral link to earn commissions when voters you invite complete views.</span>
        </li>
      </ul>

      <div class="btn-wrap">
        <a href="{{ route('voter.earnings') }}" class="btn">Go to My Earnings →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px;color:#64748b;">
        Questions? Reply to this email or visit
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
