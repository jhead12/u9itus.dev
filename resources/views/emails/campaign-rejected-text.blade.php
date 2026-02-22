❌ CAMPAIGN NOT APPROVED — {{ config('app.name', 'U9itus') }}

Thank you for submitting your campaign for review. Unfortunately, our admin team was
unable to approve it at this time.

Campaign: {{ $campaign->title }}
Status:   Not Approved
@if($campaign->governance_level)
Level:    {{ ucfirst($campaign->governance_level) }}
@endif

REASON:
{{ $reason }}

You may edit your campaign to address the feedback and resubmit it for review.
Our team typically reviews submissions within 24–48 hours.

Edit and resubmit your campaign:
{{ route('politician.dashboard') }}

---
Need clarification? Reply to this email and our team will help you bring your
campaign into compliance with U9itus content policies.

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url') }}
