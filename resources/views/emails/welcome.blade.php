<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Welcome to {{ config('app.name', 'U9itus') }}</title>
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
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #10b981; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; }
  .divider { border: none; border-top: 1px solid #334155; margin: 28px 0; }
  .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; letter-spacing: 0.5px; text-transform: uppercase; background-color: rgba(52,211,153,0.12); color: #34d399; border: 1px solid rgba(52,211,153,0.25); }
  .footer { padding: 20px 40px; border-top: 1px solid #334155; text-align: center; }
  .footer p { font-size: 12px; color: #475569; margin: 0; }
  .footer a { color: #64748b; text-decoration: none; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">

    {{-- Header --}}
    <div class="header">
      <div class="logo"><span>U9</span>itus</div>
      <div class="tagline">Political Loyalty Ads Platform</div>
    </div>

    {{-- Body --}}
    <div class="body">
      <h1>Welcome, {{ $user->first_name ?: $user->name }}! 👋</h1>

      <p>
        Your <span class="role-badge">{{ ucfirst($user->user_type ?? 'member') }}</span>
        account has been created on U9itus — the platform that connects
        @if(($user->user_type ?? '') === 'politician')
          candidates and officials with verified local voters.
        @elseif(($user->user_type ?? '') === 'voter')
          voters with political campaigns and lets you earn rewards while staying informed.
        @else
          politicians and voters.
        @endif
      </p>

      @if(($user->user_type ?? '') === 'politician')
        <p>You can now create your first campaign, set your governance level, and reach verified voters in your district. Every view costs <strong class="highlight">$0.60</strong> and campaigns require approval before going live.</p>
      @elseif(($user->user_type ?? '') === 'voter')
        <p>Start watching approved political ads in your area and <strong class="highlight">earn real money</strong> for every verified view. Your identity is protected and payouts are processed securely.</p>
      @endif

      <div class="btn-wrap">
        <a href="{{ url('/dashboard') }}" class="btn">Go to your dashboard →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px">
        <strong style="color:#cbd5e1">Need help?</strong><br />
        Reply to this email or visit our
        <a href="{{ url('/contact') }}" style="color:#34d399;text-decoration:none;">support page</a>.
        We're happy to help you get started.
      </p>
    </div>

    {{-- Footer --}}
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
