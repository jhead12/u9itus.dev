🔐 ADMIN PASSWORD RESET
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Your Admin Password Has Been Reset

The password for your administrator account on {{ config('app.name', 'U9itus') }}
has been successfully reset via the command-line interface.

ACCOUNT DETAILS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Account: {{ $admin->name }}
Email:   {{ $admin->email }}
Reset:   {{ now()->format('M j, Y \a\t g:i A T') }}

⚠️  SECURITY NOTICE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
If you did not request this password reset, your account may be 
compromised. Please contact the system administrator immediately 
and change your password again.

SIGN IN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{{ config('app.url') }}/admin/login

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Your new password was set via the 'php artisan admin:reset-password' 
command. Keep your credentials secure and do not share them with anyone.

© {{ date('Y') }} {{ config('app.name', 'U9itus') }}
{{ config('app.url') }}
