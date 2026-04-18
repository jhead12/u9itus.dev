<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Campaign Question Digest — {{ config('app.name', 'U9itus') }}</title>
<style>
  body { margin: 0; padding: 0; background-color: #0f172a; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; color: #e2e8f0; }
  .wrapper { max-width: 640px; margin: 32px auto; padding: 0 16px; }
  .card { background-color: #1e293b; border: 1px solid #334155; border-radius: 16px; overflow: hidden; }
  .header { padding: 28px 36px 22px; border-bottom: 1px solid #334155; background-color: #0f172a; }
  .logo { font-size: 28px; font-weight: 300; color: #ffffff; }
  .logo span { color: #34d399; font-weight: 700; }
  .tagline { margin-top: 6px; font-size: 12px; color: #64748b; letter-spacing: 1px; text-transform: uppercase; }
  .body { padding: 32px 36px; }
  h1 { margin: 0 0 12px; font-size: 24px; font-weight: 600; color: #ffffff; }
  p { margin: 0 0 14px; font-size: 15px; line-height: 1.65; color: #94a3b8; }
  .badge { display: inline-block; margin-bottom: 18px; padding: 6px 14px; border-radius: 999px; border: 1px solid rgba(52, 211, 153, 0.25); background-color: rgba(16, 185, 129, 0.12); color: #6ee7b7; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
  .summary { background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 18px 20px; margin: 22px 0; }
  .summary-row { display: flex; justify-content: space-between; gap: 16px; margin-bottom: 8px; font-size: 14px; }
  .summary-row:last-child { margin-bottom: 0; }
  .summary-label { color: #64748b; }
  .summary-value { color: #e2e8f0; text-align: right; }
  .question-card { background-color: #0f172a; border: 1px solid #334155; border-radius: 12px; padding: 18px 20px; margin: 14px 0; }
  .meta { display: flex; justify-content: space-between; gap: 12px; margin-bottom: 10px; font-size: 12px; color: #64748b; }
  .label { font-size: 11px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; color: #64748b; margin-bottom: 6px; }
  .question { color: #e2e8f0; font-size: 15px; line-height: 1.6; margin-bottom: 14px; }
  .response { border: 1px solid rgba(52, 211, 153, 0.2); background-color: rgba(16, 185, 129, 0.08); border-radius: 10px; padding: 14px 16px; }
  .response .label { color: #6ee7b7; }
  .response p { color: #d1fae5; margin: 0; }
  .btn-wrap { text-align: center; margin-top: 28px; }
  .btn { display: inline-block; padding: 13px 28px; background-color: #10b981; color: #ffffff; text-decoration: none; border-radius: 10px; font-weight: 600; }
  .footer { border-top: 1px solid #334155; padding: 18px 36px; text-align: center; }
  .footer p { margin: 0; font-size: 12px; color: #475569; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="card">
    <div class="header">
      <div class="logo"><span>U9</span>itus</div>
      <div class="tagline">Campaign Question Digest</div>
    </div>

    <div class="body">
      <div class="badge">Campaign End Summary</div>
      <h1>Voter questions from your completed campaign</h1>
      <p>
        Your campaign <strong style="color:#e2e8f0;">{{ $campaign->title }}</strong> has reached the end of its scheduled run.
        Here is a compiled digest of every voter question captured for this campaign.
      </p>

      <div class="summary">
        <div class="summary-row">
          <span class="summary-label">Campaign</span>
          <span class="summary-value">{{ $campaign->title }}</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">Total Questions</span>
          <span class="summary-value">{{ number_format($questions->count()) }}</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">Awaiting Public Review</span>
          <span class="summary-value">{{ number_format($questions->where('public_visibility', 'pending')->count()) }}</span>
        </div>
        <div class="summary-row">
          <span class="summary-label">Campaign Replied</span>
          <span class="summary-value">{{ number_format($questions->filter(fn ($question) => filled($question->campaign_reply))->count()) }}</span>
        </div>
      </div>

      @foreach($questions as $question)
      <div class="question-card">
        <div class="meta">
          <span>{{ $question->created_at?->format('M j, Y g:i A') }}</span>
          <span>Status: {{ ucfirst(str_replace('_', ' ', (string) $question->status)) }}</span>
        </div>

        <div class="label">Question from {{ $question->voter->full_name ?? 'Voter' }}{{ ($question->voter->email ?? null) ? ' (' . $question->voter->email . ')' : '' }}</div>
        <p class="question">{{ $question->body }}</p>

        @if(filled($question->campaign_reply))
        <div class="response">
          <div class="label">Your Official Reply</div>
          <p>{{ $question->campaign_reply }}</p>
        </div>
        @endif
      </div>
      @endforeach

      <div class="btn-wrap">
        <a href="{{ route('politician.analytics.campaign', $campaign->id) }}" class="btn">Open Campaign Analytics</a>
      </div>
    </div>

    <div class="footer">
      <p>© {{ date('Y') }} {{ config('app.name', 'U9itus') }}</p>
    </div>
  </div>
</div>
</body>
</html>