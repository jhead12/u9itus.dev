{{ config('app.name', 'U9itus') }} — Identity Verification Unsuccessful
======================================================================

Hi {{ $user->first_name ?? $user->name }},

Unfortunately, your identity verification submission could not be approved at this time.

REASON:
{{ $reason }}

Reviewed On: {{ now()->format('M j, Y') }}
Account:     {{ $user->email }}
Status:      Rejected ⚠️

To resubmit, please ensure:
  → Your government-issued ID is clear, fully visible, and not expired
  → The document matches the name on your account
  → Photos are not blurry, cropped, or obscured
  → All four corners of the document are visible

Resubmit your documents:
@if(($user->user_type ?? '') === 'politician')
{{ route('politician.profile') }}
@else
{{ route('voter.profile') }}
@endif

Need help? Reply to this email and our support team will assist you.

---
© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url', 'https://u9itus.com') }}
Unsubscribe: {{ url('/unsubscribe') }}
