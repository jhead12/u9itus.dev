<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Admin Account {{ $isNew ? 'Created' : 'Updated' }} — {{ config('app.name', 'U9itus') }}</title>
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
  .admin-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; background-color: rgba(251,191,36,0.12); color: #fbbf24; border: 1px solid rgba(251,191,36,0.25); margin-bottom: 20px; }
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; word-break: break-all; }
  .warning-box { background-color: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25); border-radius: 10px; padding: 16px 20px; margin: 20px 0; font-size: 14px; color: #fcd34d; line-height: 1.6; }
  .btn-wrap { text-align: center; margin: 28px 0; }
  .btn { display: inline-block; padding: 14px 32px; background-color: #fbbf24; color: #0f172a; font-size: 15px; font-weight: 700; text-decoration: none; border-radius: 10px; }
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
      <div class="tagline">Admin Account Notification</div>
    </div>

    {{-- Body --}}
    <div class="body">
      <div class="admin-badge">🔐 Admin Account</div>

      <h1>
        @if($isNew)
          Welcome to the Admin Panel, {{ $admin->first_name ?: $admin->name }}!
        @else
          Your Admin Account Has Been Updated
        @endif
      </h1>

      <p>
        @if($isNew)
          An administrator account has been created for you on
          <strong style="color:#fbbf24">{{ config('app.name', 'U9itus') }}</strong>.
          You now have full access to manage campaigns, users, fraud detection,
          and platform settings.
        @else
          Your administrator account on <strong style="color:#fbbf24">{{ config('app.name', 'U9itus') }}</strong>
          has been updated. Your roles and settings have been reconfigured by the system.
        @endif
      </p>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Name</span>
          <span class="info-value">{{ $admin->name }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Email</span>
          <span class="info-value">{{ $admin->email }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Role</span>
          <span class="info-value" style="color:#fbbf24;">Administrator</span>
        </div>
        <div class="info-row">
          <span class="info-label">Account Created</span>
          <span class="info-value">{{ $admin->created_at?->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A') }}</span>
        </div>
      </div>

      @if($isNew && $tempPass)
      <div class="warning-box">
        ⚠️ <strong>Temporary Password:</strong> <code style="background:#1e293b;padding:2px 6px;border-radius:4px;font-size:13px;">{{ $tempPass }}</code><br />
        Please log in and change your password immediately for security.
      </div>
      @endif

      <div class="warning-box">
        🔒 If you did not request this admin account, please contact your platform owner
        immediately. Admin credentials should be kept strictly confidential.
      </div>

      <div class="btn-wrap">
        <a href="{{ route('admin.dashboard') }}" class="btn">Go to Admin Dashboard →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px">
        Admin portal URL:
        <a href="{{ route('admin.login') }}" style="color:#34d399;text-decoration:none;">{{ route('admin.login') }}</a>
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
