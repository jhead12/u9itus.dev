Account Reactivated - {{ config('app.name', 'U9itus') }}

Hello {{ $user->first_name ?? $user->name }},

your account has been reactivated by an administrator.
You can sign in again and continue using the platform.

Open app:
{{ config('app.url') }}

---
{{ config('app.name', 'U9itus') }}
