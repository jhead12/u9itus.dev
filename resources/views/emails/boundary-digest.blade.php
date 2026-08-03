<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Your saved-places update — {{ config('app.name', 'U9itus') }}</title>
<style>
  body { margin: 0; padding: 0; background-color: #0f172a; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #e2e8f0; }
  .wrapper { max-width: 600px; margin: 40px auto; padding: 0 16px; }
  .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; overflow: hidden; }
  .header { background-color: #0f172a; padding: 32px 40px 24px; border-bottom: 1px solid #334155; text-align: center; }
  .logo { font-size: 28px; font-weight: 300; letter-spacing: -0.5px; color: #ffffff; }
  .logo span { color: #34d399; font-weight: 700; }
  .tagline { margin-top: 6px; font-size: 12px; color: #64748b; letter-spacing: 1px; text-transform: uppercase; }
  .body { padding: 36px 40px; }
  h1 { margin: 0 0 8px; font-size: 22px; font-weight: 600; color: #ffffff; }
  .period { font-size: 13px; color: #64748b; margin: 0 0 24px; }
  h2.boundary { font-size: 16px; color: #34d399; margin: 28px 0 12px; }
  .candidate { border-top: 1px solid #334155; padding: 14px 0; }
  .candidate:first-of-type { border-top: none; }
  .candidate-name { font-size: 15px; font-weight: 600; color: #ffffff; margin: 0 0 6px; }
  .item { font-size: 13px; color: #94a3b8; margin: 0 0 4px; }
  .item a { color: #7dd3fc; text-decoration: none; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; background-color: rgba(52,211,153,0.15); color: #34d399; margin-right: 6px; }
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
      <h1>Your saved places, {{ $voter->full_name ?: 'there' }}</h1>
      <p class="period">{{ $periodLabel }}</p>

      @foreach ($sections as $section)
        <h2 class="boundary">📍 {{ $section['boundary']->label }}</h2>

        @foreach ($section['candidates'] as $row)
          <div class="candidate">
            <p class="candidate-name">{{ $row['politician']->full_name }}</p>

            @foreach ($row['endorsements'] as $endorsement)
              <p class="item"><span class="badge">Endorsement</span>{{ $endorsement->label }}</p>
            @endforeach

            @foreach ($row['news'] as $article)
              <p class="item">
                <a href="{{ $article->source_url }}">{{ $article->headline }}</a>
              </p>
            @endforeach
          </div>
        @endforeach
      @endforeach

      <div class="btn-wrap">
        <a href="{{ url('/map') }}" class="btn">View the Map →</a>
      </div>

      <hr class="divider" />

      <p style="font-size:13px;color:#64748b;">
        You're receiving this because you opted into weekly saved-places updates.
        Manage this anytime from your notification settings.
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
