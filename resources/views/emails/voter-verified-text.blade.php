Your identity has been verified — {{ config('app.name', 'U9itus') }}

Hi {{ $voter->full_name ?: 'there' }},

Great news! Your identity verification through Authentic User Verifier
(powered by Stripe Connect) is now complete. Your account is fully
active and you can start earning from political ad views right away.

What you can do now:
- Earn $0.50 per completed ad view — deposited weekly to your connected account
- Watch political messages from candidates in your district
- Share your referral link to earn commissions

Go to your earnings page:
{{ route('voter.earnings') }}

---
Questions? Visit {{ config('app.url') }} or reply to this email.

{{ config('app.name', 'U9itus') }}
