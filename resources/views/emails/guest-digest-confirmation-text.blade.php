Confirm your saved-places updates — {{ config('app.name', 'U9itus') }}

Hi{{ $voter->full_name && $voter->full_name !== 'Guest Subscriber' ? ' ' . $voter->full_name : '' }},

You (or someone using this email address) asked for weekly updates about the
districts and cities you've saved on the U9itus map — new candidate news,
endorsements, and other activity for those places.

Confirm here (expires in 3 days):
{{ $confirmUrl }}

If you didn't request this, you can safely ignore this email — no updates
will be sent unless you confirm.

A quick look at U9itus:
- Track any district or city — officeholders, election news, and endorsements in one place
- Watch video messages from politicians running near you, and earn money for every view
- Save the places you care about on the map to get digests like this one whenever things change

---
Questions? Visit {{ config('app.url') }} or reply to this email.

{{ config('app.name', 'U9itus') }}
