<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Ballot measures for {{ $state }} are now available</title>
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
  .tag { display: inline-block; padding: 6px 12px; border-radius: 999px; background: rgba(52,211,153,0.12); border: 1px solid rgba(52,211,153,0.35); color: #6ee7b7; font-size: 12px; font-weight: 700; letter-spacing: 0.3px; margin-bottom: 14px; }
  .btn-wrap { text-align: center; margin: 24px 0; }
  .btn { display: inline-block; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-size: 15px; font-weight: 600; background-color: #0891b2; color: #ffffff; }
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
      <div class="tag">Ballot measures</div>
      <h1>{{ $state }} ballot measures are ready</h1>
      <p>You asked to be notified when ballot measures for <strong style="color:#e2e8f0;">{{ $state }}</strong> became available. We now have {{ $count }} on file, with plain-language explanations of what a Yes or No vote does.</p>
      <div class="btn-wrap">
        <a class="btn" href="{{ $url }}">See {{ $state }} ballot measures</a>
      </div>
      <p style="font-size:13px;">You're receiving this once. We won't email you about {{ $state }} again unless you ask.</p>
    </div>
    <div class="footer">
      <p>U9itus &middot; nonpartisan civic information</p>
    </div>
  </div>
</div>
</body>
</html>
