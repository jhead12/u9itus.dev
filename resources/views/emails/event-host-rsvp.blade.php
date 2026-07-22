<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>New RSVP — {{ config('app.name', 'U9itus') }}</title>
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
  .info-box { background-color: #0f172a; border: 1px solid #334155; border-radius: 10px; padding: 20px 24px; margin: 20px 0; }
  .info-row { display: flex; justify-content: space-between; margin-bottom: 10px; font-size: 14px; }
  .info-row:last-child { margin-bottom: 0; }
  .info-label { color: #64748b; }
  .info-value { color: #e2e8f0; font-weight: 500; text-align: right; max-width: 60%; }
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
      <div class="tagline">Civic Engagement Platform</div>
    </div>

    <div class="body">
      <h1>New RSVP received</h1>

      <p>
        <span class="highlight">{{ $rsvp->user->name }}</span> responded to your event
        "<span class="highlight">{{ $event->title }}</span>".
      </p>

      <div class="info-box">
        <div class="info-row">
          <span class="info-label">Response</span>
          <span class="info-value">{{ $rsvp->status->label() }}</span>
        </div>
        <div class="info-row">
          <span class="info-label">Guests</span>
          <span class="info-value">{{ $rsvp->guest_count }}</span>
        </div>
        @if($rsvp->notes)
        <div class="info-row">
          <span class="info-label">Note</span>
          <span class="info-value">{{ $rsvp->notes }}</span>
        </div>
        @endif
        <div class="info-row">
          <span class="info-label">Total attending</span>
          <span class="info-value">{{ $event->attendingCount() }}</span>
        </div>
        @if($event->capacity)
        <div class="info-row">
          <span class="info-label">Capacity</span>
          <span class="info-value">{{ $event->capacity }}</span>
        </div>
        @endif
      </div>

      <div class="btn-wrap">
        <a href="{{ route('events.show', $event->slug) }}" class="btn">Manage Event →</a>
      </div>
    </div>

    <div class="footer">
      <p>
        © {{ date('Y') }} {{ config('app.name', 'U9itus') }} · Civic Engagement Platform<br />
        <a href="{{ url('/') }}">{{ parse_url(config('app.url', 'https://u9itus.com'), PHP_URL_HOST) }}</a>
      </p>
    </div>

  </div>
</div>
</body>
</html>
