<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\AdminSecurityAuditLog;
use App\Services\PlatformSettingsService;
use App\Services\TwoFactorService;
use App\Services\UserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Admin Two-Factor Challenge Controller
 *
 * Handles the post-login TOTP challenge for administrators. Setup, disable,
 * and recovery-code rotation live in AdminController under /admin/security/2fa.
 */
class AdminTwoFactorController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactorService,
        private readonly UserRoleService $roleService,
    ) {}

    public function showChallenge(Request $request)
    {
        $user = $request->user();

        if (! $user || ! $this->roleService->hasRole($user, 'admin')) {
            abort(403);
        }

        $isEnforced = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (! $isEnforced) {
            return redirect()->route('admin.dashboard');
        }

        if (! $user->hasAdminTwoFactorEnabled()) {
            return redirect()
                ->route('admin.2fa.setup')
                ->with('warning', 'Two-factor authentication is required before continuing.');
        }

        return view('standalone.auth.admin-2fa-challenge');
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if (! $user || ! $this->roleService->hasRole($user, 'admin') || ! $user->hasAdminTwoFactorEnabled()) {
            abort(403);
        }

        $inputCode   = (string) $request->input('code');
        $verifiedBy  = null;

        if (preg_match('/^\d{6}$/', $inputCode) === 1) {
            if ($this->twoFactorService->verifyCode((string) $user->admin_two_factor_secret, $inputCode)) {
                $verifiedBy = 'totp';
            }
        } else {
            $existingCodes = (array) ($user->admin_two_factor_recovery_codes ?? []);
            $remainingCodes = $this->twoFactorService->consumeRecoveryCode($existingCodes, $inputCode);

            if ($remainingCodes !== null) {
                $user->forceFill([
                    'admin_two_factor_recovery_codes' => $remainingCodes,
                ])->save();

                $verifiedBy = 'recovery_code';
            }
        }

        if ($verifiedBy === null) {
            AdminSecurityAuditLog::record($user, 'admin.2fa.challenge.failed', [], $request);
            return back()->withErrors(['code' => 'Invalid authenticator or recovery code. Please try again.']);
        }

        $request->session()->put('admin_2fa_verified_user_id', (int) $user->id);
        $request->session()->put('admin_2fa_verified_at', now()->toIso8601String());

        AdminSecurityAuditLog::record(
            $user,
            'admin.2fa.challenge.passed',
            ['verified_by' => $verifiedBy],
            $request
        );

        return redirect()->route('admin.dashboard')
            ->with('success', 'Two-factor verification complete.');
    }
}
