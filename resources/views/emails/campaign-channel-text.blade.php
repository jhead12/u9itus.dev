U9itus — Political Loyalty Ads Platform

{{ $campaign['title'] ?? 'A new campaign for you' }}

@if(!empty($recipient['name']))
Hello {{ $recipient['name'] }},
@endif

@if(!empty($campaign['message_summary']))
{{ $campaign['message_summary'] }}
@endif

You're receiving this because a campaign on U9itus is reaching voters in your
area. Watch the full message to earn and have your voice counted.

View on U9itus: {{ url('/map') }}

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ parse_url(config('app.url', 'https://u9itus.com'), PHP_URL_HOST) }}

Unsubscribe: {{ url('/unsubscribe') }}