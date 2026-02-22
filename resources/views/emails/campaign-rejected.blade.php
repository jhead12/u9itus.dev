<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Campaign Not Approved — {{ config('app.name', 'U9itus') }}</title>
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
  .highlight { color: #f87171; font-weight: 500; }
  .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; letter-spacing: 0.5px; text-transform: uppercase; background-color: rgba(248,113,113,0.12); color: #f87171; border: 1px solid rgba(248,113,113,0.30); margin-bottom: 20px; }
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 65%; }
  .reason-box { background-color: rgba(248,113,113,0.07); border: 1px solid rgba(248,113,113,0.25); border-radius: 10px; padding: 16px 20px; margin: 20px 0; font-size: 14px; color: #fca5a5; line-height: 1.6; }
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

    {{-- Header --}}
    <div class="header">
      <div class="logo"><span>U9</span>itus</div>
      <div class="tagline">Political Loyalty Ads Platform</div>
    </div>

    {{-- Body --}}
    <div class="body">
      <div class="status-badge">❌ Not Approved</div>

      <h1>Campaign Review Update</h1>

      <p>
        Thank you for submitting your campaign for review. Unfortunately, after careful
        evaluation our admin team was unable to approve it at this time.
      </p>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Campaign</span>
          <span class="info-value">{{ $campaign->title }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Status</span>
          <span class="info-value" style="color:#f87171;">Not Approved</span>
        </div>
        @if($campaign->governance_level)
        <div class="info-row">
          <span class="info-label">Governance Level</span>
          <span class="info-value">{{ ucfirst($campaign->governance_level) }}</span>
        </div>
        @endif
      </div>

      <p><strong style="color:#cbd5e1">Reason provided:</strong></p>
      <div class="reason-box">
        {{ $reason }}
      </div>

      <p>
        You may edit your campaign to address the feedback and resubmit it for review.
        Our team typically reviews submissions within <span class="highlight">24–48 hours</span>.
      </p>

      <div class="btn-wrap">
        <a href="{{ route('politician.dashboard') }}" class="btn">Edit &amp; Resubmit Campaign →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px">
        <strong style="color:#cbd5e1">Need clarification?</strong><br />
        Reply to this email and our team will provide additional guidance on making your
        campaign compliant with U9itus content policies.
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
