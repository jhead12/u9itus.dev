<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Complete Authentic User Verifier</title>
<style>
  body { margin: 0; padding: 0; background-color: #0f172a; font-family: Helvetica, Arial, sans-serif; color: #e2e8f0; }
  .wrapper { max-width: 580px; margin: 40px auto; padding: 0 16px; }
  .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; overflow: hidden; }
  .header { background-color: #0f172a; padding: 28px 36px 20px; border-bottom: 1px solid #334155; text-align: center; }
  .logo { font-size: 28px; font-weight: 300; color: #ffffff; }
  .logo span { color: #34d399; font-weight: 700; }
  .body { padding: 32px 36px; }
  h1 { margin: 0 0 12px; font-size: 22px; font-weight: 600; color: #ffffff; }
  p { margin: 0 0 14px; font-size: 15px; line-height: 1.65; color: #94a3b8; }
  .tag { display: inline-block; padding: 6px 12px; border-radius: 999px; background: rgba(56,189,248,0.12); border: 1px solid rgba(56,189,248,0.35); color: #7dd3fc; font-size: 12px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 14px; }
  .box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 16px 18px; margin: 18px 0; }
  .btn-wrap { text-align: center; margin: 24px 0; }
  .btn { display: inline-block; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-size: 15px; font-weight: 600; }
  .btn-primary { background-color: #0891b2; color: #ffffff; }
  .btn-secondary { background-color: rgba(148,163,184,0.15); border: 1px solid #475569; color: #cbd5e1; margin-left: 8px; }
  .footer { padding: 18px 36px; border-top: 1px solid #334155; text-align: center; }
  .footer p { margin: 0; font-size: 12px; color: #64748b; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <div class="logo"><span>U9</span>itus</div>
    </div>
    <div class="body">
      <div class="tag">Authentic User Verifier</div>
      <h1>Action needed for payouts</h1>
      <p>Hello {{ $user->first_name ?? $user->name }},</p>
      <p>Your account was previously verified under a legacy process. To continue receiving payouts, complete the new <strong style="color:#7dd3fc;">Authentic User Verifier</strong> flow powered by Stripe Connect.</p>

      <div class="box">
        <p style="margin-bottom:8px;"><strong style="color:#e2e8f0;">What changes:</strong></p>
        <p style="margin-bottom:4px;">1. Legacy verification records remain read-only.</p>
        <p style="margin-bottom:4px;">2. New payout verification is handled through Stripe Connect.</p>
        <p style="margin-bottom:0;">3. Once complete, your payout account is marked active automatically.</p>
      </div>

      <div class="btn-wrap">
        <a href="{{ $startUrl }}" class="btn btn-primary">Start Authentic User Verifier</a>
        <a href="{{ $payoutUrl }}" class="btn btn-secondary">Open Earnings</a>
      </div>

      <p style="font-size:13px;">If you have questions, reply to this email and our support team will help you.</p>
    </div>
    <div class="footer">
      <p>{{ config('app.name', 'U9itus') }} - Political Loyalty Ads Platform</p>
    </div>
  </div>
</div>
</body>
</html>
