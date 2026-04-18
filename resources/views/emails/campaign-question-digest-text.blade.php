{{ config('app.name', 'U9itus') }} — Campaign Question Digest
===========================================================

Your campaign "{{ $campaign->title }}" has reached the end of its scheduled run.
Below is the compiled voter question digest for this campaign.

SUMMARY
  Campaign               : {{ $campaign->title }}
  Total Questions        : {{ number_format($questions->count()) }}
  Awaiting Public Review : {{ number_format($questions->where('public_visibility', 'pending')->count()) }}
  Campaign Replied       : {{ number_format($questions->filter(fn ($question) => filled($question->campaign_reply))->count()) }}

@foreach($questions as $index => $question)
QUESTION {{ $index + 1 }}
  Sent At     : {{ $question->created_at?->format('M j, Y g:i A') }}
  Status      : {{ ucfirst(str_replace('_', ' ', (string) $question->status)) }}
  From        : {{ $question->voter->full_name ?? 'Voter' }}{{ ($question->voter->email ?? null) ? ' (' . $question->voter->email . ')' : '' }}
  Question    : {{ $question->body }}
@if(filled($question->campaign_reply))
  Your Reply  : {{ $question->campaign_reply }}
@endif

@endforeach
Open campaign analytics:
{{ route('politician.analytics.campaign', $campaign->id) }}