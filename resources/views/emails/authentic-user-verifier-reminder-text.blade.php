Action needed for payouts

Hello {{ $user->first_name ?? $user->name }},

Your account was previously verified under a legacy process. To continue receiving payouts, complete the new Authentic User Verifier flow powered by Stripe Connect.

What changes:
- Legacy verification records remain read-only.
- New payout verification is handled through Stripe Connect.
- Once complete, your payout account is marked active automatically.

Start Authentic User Verifier: {{ $startUrl }}
Open Earnings: {{ $payoutUrl }}

If you have questions, reply to this email and our support team will help you.
