🔐 ADMIN ACCOUNT {{ $isNew ? 'CREATED' : 'UPDATED' }} — {{ config('app.name', 'U9itus') }}

@if($isNew)
Welcome to the Admin Panel, {{ $admin->first_name ?: $admin->name }}!

An administrator account has been created for you on {{ config('app.name', 'U9itus') }}.
You now have full access to manage campaigns, users, fraud detection, and platform settings.
@else
Your administrator account on {{ config('app.name', 'U9itus') }} has been updated.
Your roles and settings have been reconfigured by the system.
@endif

Name:    {{ $admin->name }}
Email:   {{ $admin->email }}
Role:    Administrator
Created: {{ $admin->created_at?->format('M j, Y g:i A') ?? now()->format('M j, Y g:i A') }}

@if($isNew && $tempPass)
⚠ TEMPORARY PASSWORD: {{ $tempPass }}
Please log in and change your password immediately for security.

@endif
⚠ If you did not request this admin account, please contact your platform owner
immediately. Admin credentials should be kept strictly confidential.

Admin Dashboard:
{{ route('admin.dashboard') }}

Admin Login:
{{ route('admin.login') }}

---
© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url') }}
