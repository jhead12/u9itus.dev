<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>New User Registered — {{ config('app.name', 'U9itus') }}</title>
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
  .role-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; }
  .role-politician { background-color: rgba(99,102,241,0.15); color: #a5b4fc; border: 1px solid rgba(99,102,241,0.30); }
  .role-voter { background-color: rgba(52,211,153,0.12); color: #34d399; border: 1px solid rgba(52,211,153,0.25); }
  .role-admin { background-color: rgba(251,191,36,0.12); color: #fbbf24; border: 1px solid rgba(251,191,36,0.25); }
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; word-break: break-all; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #6366f1; color: #ffffff; font-size: 15px; font-weight: 600; text-decoration: none; border-radius: 10px; }
  .divider { border: none; border-top: 1px solid #334155; margin: 28px 0; }
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
      <div class="tagline">Admin Notification</div>
    </div>

    {{-- Body --}}
    <div class="body">
      <h1>🔔 New {{ ucfirst($newUser->user_type ?? 'User') }} Registration</h1>

      <p>
        A new
        <span class="role-badge role-{{ $newUser->user_type ?? 'voter' }}">{{ ucfirst($newUser->user_type ?? 'user') }}</span>
        has just registered on the platform and requires your attention.
      </p>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Name</span>
          <span class="info-value">{{ $newUser->name }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Email</span>
          <span class="info-value">{{ $newUser->email }}</span>
        </div>
        @if($newUser->phone)
        <div class="info-row">
          <span class="info-label">Phone</span>
          <span class="info-value">{{ $newUser->phone }}</span>
        </div>
        @endif
        @if($newUser->state)
        <div class="info-row">
          <span class="info-label">State</span>
          <span class="info-value">{{ strtoupper($newUser->state) }}</span>
        </div>
        @endif
        <div class="info-row">
          <span class="info-label">Role</span>
          <span class="info-value">{{ ucfirst($newUser->user_type ?? 'user') }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Registered At</span>
          <span class="info-value">{{ $newUser->created_at?->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A') }}</span>
        </div>
        @if($newUser->user_type === 'politician' && $newUser->politician)
        <div class="info-row">
          <span class="info-label">Office</span>
          <span class="info-value">{{ $newUser->politician->political_office ?? '—' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Party</span>
          <span class="info-value">{{ $newUser->politician->party_affiliation ?? '—' }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Governance</span>
          <span class="info-value">{{ ucfirst($newUser->politician->governance_level ?? '—') }}</span>
        </div>
        @endif
      </div>

      @if($newUser->user_type === 'politician')
      <p>
        Politicians require <strong style="color:#a5b4fc">KYC verification</strong> and campaign approval
        before reaching voters. Please review their profile in the admin panel.
      </p>
      @else
      <p>
        The voter's account is active. Monitor for any unusual activity in the fraud dashboard.
      </p>
      @endif

      <div class="btn-wrap">
        <a href="{{ route('admin.users.show', $newUser->id) }}" class="btn">View User in Admin Panel →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px; color:#475569;">
        This is an automated notification sent to all admin accounts when a new user registers.
        Manage notification preferences in
        <a href="{{ route('admin.settings') }}" style="color:#64748b;">Admin Settings</a>.
      </p>
    </div>

    {{-- Footer --}}
    <div class="footer">
      <p>
        © {{ date('Y') }} {{ config('app.name', 'U9itus') }} · Political Loyalty Ads Platform<br />
        <a href="{{ url('/') }}">{{ parse_url(config('app.url', 'https://u9itus.com'), PHP_URL_HOST) }}</a>
      </p>
    </div>

  </div>
</div>
</body>
</html>
