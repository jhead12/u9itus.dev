You're enrolled in the Early-bank referral program

Hi {{ $voter->full_name ?: 'there' }},

Thanks for signing up through an Early-bank referral link. Your account
is now linked to the member who invited you, and you can start earning
from political ad views immediately.

Nothing changes for your payouts — you still earn the full $0.25 per
verified view. The referral linkage simply lets your referrer earn a
small reward from Early-bank for inviting you.

Voter ID: {{ $voter->uuid }}
Referred By (Early-bank member): {{ $earlybankMemberId }}
Linked On: {{ optional($voter->earlybank_linked_at)->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A') }}

Go to My Earnings: {{ route('voter.earnings') }}

Questions about Early-bank? Visit https://early-bank.com or reply to
this email.

— {{ config('app.name', 'U9itus') }}
{{ parse_url(config('app.url', 'https://u9itus.com'), PHP_URL_HOST) }}
