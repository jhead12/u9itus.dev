🔔 NEW {{ strtoupper($newUser->user_type ?? 'USER') }} REGISTRATION — {{ config('app.name', 'U9itus') }}

A new {{ ucfirst($newUser->user_type ?? 'user') }} has just registered on the platform.

Name:      {{ $newUser->name }}
Email:     {{ $newUser->email }}
@if($newUser->phone)
Phone:     {{ $newUser->phone }}
@endif
@if($newUser->state)
State:     {{ strtoupper($newUser->state) }}
@endif
Role:      {{ ucfirst($newUser->user_type ?? 'user') }}
Registered: {{ $newUser->created_at?->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A') }}
@if($newUser->user_type === 'politician' && $newUser->politician)

POLITICIAN DETAILS:
Office:     {{ $newUser->politician->political_office ?? '—' }}
Party:      {{ $newUser->politician->party_affiliation ?? '—' }}
Governance: {{ ucfirst($newUser->politician->governance_level ?? '—') }}
@endif

@if($newUser->user_type === 'politician')
Politicians require KYC verification and campaign approval before reaching voters.
@else
Monitor for unusual activity in the fraud dashboard.
@endif

View user in admin panel:
{{ route('admin.users.show', $newUser->id) }}

---
This is an automated admin notification.

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url') }}
