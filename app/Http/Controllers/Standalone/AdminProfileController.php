<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\AdminSecurityAuditLog;
use App\Services\AdminTwoFactorService;
use App\Services\PlatformSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Admin's own account: profile + admin TOTP (2FA) management.
 *
 * Split out of AdminController — covers the admin profile page, profile
 * updates, and the admin two-factor setup/enable/disable/recovery-code flow.
 * The 2FA routes live in their own route group (role:admin only, no onboarding
 * or 2FA-enforcement middleware) so setup/challenge can complete unimpeded.
 */
class AdminProfileController extends Controller
{
    /**
     * Show the admin's own profile page.
     */
    public function profile()
    {
        $user = auth()->user();

        $adminTwoFactorEnforced = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        return view('standalone.admin.profile', compact('user', 'adminTwoFactorEnforced'));
    }

    /**
     * Show admin TOTP setup page.
     */
    public function twoFactorSetup(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $user = $request->user();
        $isEnabled = $user->hasAdminTwoFactorEnabled();
        $setupSecret = null;
        $otpAuthUrl = null;
        $otpQrSvg = null;
        $newRecoveryCodes = $request->session()->get('admin_2fa_new_recovery_codes', []);

        if (!$isEnabled) {
            $setupSecret = (string) $request->session()->get('admin_2fa_setup_secret');

            if ($setupSecret === '') {
                $setupSecret = $twoFactorService->generateSecret();
                $request->session()->put('admin_2fa_setup_secret', $setupSecret);
            }

            $otpAuthUrl = $twoFactorService->getOtpAuthUrl($user, $setupSecret);
            $otpQrSvg = $twoFactorService->renderOtpAuthQrSvg($otpAuthUrl);
        }

        return view('standalone.admin.security.2fa-setup', compact('isEnabled', 'setupSecret', 'otpAuthUrl', 'otpQrSvg', 'newRecoveryCodes'));
    }

    /**
     * Confirm and enable admin TOTP.
     */
    public function enableTwoFactor(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        $user = $request->user();

        if ($user->hasAdminTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication is already enabled.']);
        }

        $secret = (string) $request->session()->get('admin_2fa_setup_secret', '');

        if ($secret === '') {
            return back()->withErrors(['code' => 'Setup secret expired. Reload the page and try again.']);
        }

        if (!$twoFactorService->verifyCode($secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid authenticator code for setup confirmation.']);
        }

        try {
            $recoveryCodes = $twoFactorService->generateRecoveryCodes();

            // Clear any stale encrypted values first (prevents DecryptException
            // when a previous key was used to encrypt existing column data).
            \DB::table('users')->where('id', $user->id)->update([
                'admin_two_factor_secret'         => null,
                'admin_two_factor_confirmed_at'   => null,
                'admin_two_factor_recovery_codes' => null,
            ]);
            $user->forceFill([
                'admin_two_factor_secret' => $secret,
                'admin_two_factor_confirmed_at' => now(),
                'admin_two_factor_recovery_codes' => $recoveryCodes,
            ])->save();

            $request->session()->forget('admin_2fa_setup_secret');
            $request->session()->put('admin_2fa_verified_user_id', (int) $user->id);
            $request->session()->put('admin_2fa_verified_at', now()->toIso8601String());
            $request->session()->flash('admin_2fa_new_recovery_codes', $recoveryCodes);

            Log::info('Admin enabled two-factor authentication', [
                'admin_id' => $user->id,
            ]);

            // Audit logging is best-effort; never break the user-facing enable flow.
            try {
                AdminSecurityAuditLog::record(
                    $user,
                    'admin.2fa.enabled',
                    ['recovery_code_count' => count($recoveryCodes)],
                    $request
                );
            } catch (\Throwable $auditError) {
                Log::error('Admin 2FA enable: audit log write failed', [
                    'admin_id' => $user->id,
                    'error' => $auditError->getMessage(),
                    'exception' => get_class($auditError),
                ]);
            }
        } catch (\Throwable $e) {
            // Log to both file AND stderr so Railway's log stream captures it.
            $errorContext = [
                'admin_id'  => $user?->id,
                'error'     => $e->getMessage(),
                'exception' => get_class($e),
                'file'      => $e->getFile() . ':' . $e->getLine(),
            ];
            Log::error('Admin 2FA enable failed', $errorContext);
            // Write to stderr so Railway stdout log captures it in production.
            fwrite(STDERR, '[Admin 2FA] enable failed: ' . $e->getMessage() . ' (' . get_class($e) . ') at ' . $e->getFile() . ':' . $e->getLine() . PHP_EOL);

            return back()->withErrors([
                'code' => 'Unable to enable two-factor authentication right now. Please try again or contact support.',
            ]);
        }

        return back()->with('success', 'Two-factor authentication enabled successfully.');
    }

    /**
     * Disable admin TOTP after credential verification.
     */
    public function disableTwoFactor(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'digits:6'],
        ]);

        $isEnforced = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($isEnforced) {
            return back()->withErrors(['code' => 'Global admin 2FA policy is enabled. Disable policy before disabling your authenticator.']);
        }

        $user = $request->user();

        if (!$user->hasAdminTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication is not enabled for this account.']);
        }

        if (!$twoFactorService->verifyCode((string) $user->admin_two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid authenticator code.']);
        }

        $user->forceFill([
            'admin_two_factor_secret' => null,
            'admin_two_factor_confirmed_at' => null,
            'admin_two_factor_recovery_codes' => null,
        ])->save();

        $request->session()->forget(['admin_2fa_verified_user_id', 'admin_2fa_verified_at']);

        Log::info('Admin disabled two-factor authentication', [
            'admin_id' => $user->id,
        ]);

        AdminSecurityAuditLog::record($user, 'admin.2fa.disabled', [], $request);

        return back()->with('success', 'Two-factor authentication disabled successfully.');
    }

    /**
     * Rotate recovery codes after password + authenticator verification.
     */
    public function rotateRecoveryCodes(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if (!$user->hasAdminTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication must be enabled before rotating recovery codes.']);
        }

        $inputCode = (string) $request->input('code');
        $method = null;

        if (preg_match('/^\d{6}$/', $inputCode) === 1) {
            if (!$twoFactorService->verifyCode((string) $user->admin_two_factor_secret, $inputCode)) {
                return back()->withErrors(['code' => 'Invalid authenticator code.']);
            }

            $method = 'totp';
        } else {
            $existingCodes = (array) ($user->admin_two_factor_recovery_codes ?? []);
            $remainingCodes = $twoFactorService->consumeRecoveryCode($existingCodes, $inputCode);

            if ($remainingCodes === null) {
                return back()->withErrors(['code' => 'Invalid recovery code.']);
            }

            $method = 'recovery_code';
        }

        $newRecoveryCodes = $twoFactorService->generateRecoveryCodes();

        $user->forceFill([
            'admin_two_factor_recovery_codes' => $newRecoveryCodes,
        ])->save();

        $request->session()->flash('admin_2fa_new_recovery_codes', $newRecoveryCodes);

        AdminSecurityAuditLog::record(
            $user,
            'admin.2fa.recovery_codes.rotated',
            [
                'verified_by' => $method,
                'recovery_code_count' => count($newRecoveryCodes),
            ],
            $request
        );

        return back()->with('success', 'Recovery codes rotated successfully. Save your new codes now.');
    }

    /**
     * Update admin name, email, or password.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name'            => ['required', 'string', 'max:255'],
            'last_name'             => ['required', 'string', 'max:255'],
            'email'                 => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'current_password'      => ['nullable', 'current_password'],
            'password'              => ['nullable', 'min:8', 'confirmed'],
        ]);

        $user->first_name = $validated['first_name'];
        $user->last_name  = $validated['last_name'];
        $user->email      = $validated['email'];

        if (! empty($validated['password'])) {
            $user->password = bcrypt($validated['password']);
        }

        $user->save();

        return back()->with('success', 'Profile updated successfully.');
    }
}