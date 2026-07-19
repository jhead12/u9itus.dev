<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Services\TwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class TwoFactorController extends Controller
{
    // ── Setup ────────────────────────────────────────────────────────────────

    public function showSetup(Request $request, TwoFactorService $twoFactor)
    {
        $user = $request->user();

        $newRecoveryCodes = $request->session()->get('2fa_new_recovery_codes', []);
        $isEnabled        = $user->hasTwoFactorEnabled();

        // Generate a fresh secret and QR if not yet confirmed.
        $setupSecret = null;
        $qrSvg       = null;

        if (!$isEnabled) {
            $setupSecret = $request->session()->get('2fa_setup_secret');

            if (!$setupSecret) {
                $setupSecret = $twoFactor->generateSecret();
                $request->session()->put('2fa_setup_secret', $setupSecret);
            }

            $otpAuthUrl = $twoFactor->getOtpAuthUrl($user, $setupSecret);
            $qrSvg      = $twoFactor->renderOtpAuthQrSvg($otpAuthUrl);
        }

        return view('standalone.auth.2fa-setup', compact('isEnabled', 'setupSecret', 'qrSvg', 'newRecoveryCodes'));
    }

    public function enable(Request $request, TwoFactorService $twoFactor)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:8'],
        ]);

        $user   = $request->user();
        $secret = (string) $request->session()->get('2fa_setup_secret', '');

        if (!$secret) {
            return back()->withErrors(['code' => 'Setup session expired. Please try again.']);
        }

        if (!$twoFactor->verifyCode($secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid code. Please check your authenticator and try again.']);
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();

        try {
            $user->forceFill([
                'two_factor_secret'         => $secret,
                'two_factor_confirmed_at'   => now(),
                'two_factor_recovery_codes' => $recoveryCodes,
            ])->save();

            $request->session()->forget('2fa_setup_secret');
            $request->session()->put('2fa_verified_user_id', (int) $user->id);
            $request->session()->put('2fa_verified_at', now()->toIso8601String());
            $request->session()->flash('2fa_new_recovery_codes', $recoveryCodes);
        } catch (\Throwable $e) {
            Log::error('2FA enable failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return back()->withErrors(['code' => 'Failed to enable two-factor authentication. Please try again.']);
        }

        return redirect()->route('2fa.setup')->with('success', 'Two-factor authentication enabled. Save your recovery codes.');
    }

    public function disable(Request $request, TwoFactorService $twoFactor)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication is not enabled.']);
        }

        if (!$twoFactor->verifyCode((string) $user->two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Invalid authenticator code.']);
        }

        $user->forceFill([
            'two_factor_secret'         => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $request->session()->forget(['2fa_verified_user_id', '2fa_verified_at']);

        return back()->with('success', 'Two-factor authentication has been disabled.');
    }

    public function rotateRecoveryCodes(Request $request, TwoFactorService $twoFactor)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if (!$user->hasTwoFactorEnabled()) {
            return back()->withErrors(['code' => 'Two-factor authentication is not enabled.']);
        }

        $inputCode  = (string) $request->input('code');
        $verified   = false;

        if (preg_match('/^\d{6}$/', $inputCode) === 1) {
            $verified = $twoFactor->verifyCode((string) $user->two_factor_secret, $inputCode);
        } else {
            $existingCodes  = (array) ($user->two_factor_recovery_codes ?? []);
            $remainingCodes = $twoFactor->consumeRecoveryCode($existingCodes, $inputCode);

            if ($remainingCodes !== null) {
                $user->forceFill(['two_factor_recovery_codes' => $remainingCodes])->save();
                $verified = true;
            }
        }

        if (!$verified) {
            return back()->withErrors(['code' => 'Invalid code.']);
        }

        $newCodes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $newCodes])->save();
        $request->session()->flash('2fa_new_recovery_codes', $newCodes);

        return redirect()->route('2fa.setup')->with('success', 'Recovery codes rotated. Save your new codes now.');
    }

    // ── Challenge ────────────────────────────────────────────────────────────

    public function showChallenge(Request $request)
    {
        $user = $request->user();

        if (!$user || $user->hasRole('admin')) {
            abort(403);
        }

        if (!$user->hasTwoFactorEnabled()) {
            return redirect()->route('2fa.setup');
        }

        return view('standalone.auth.2fa-challenge');
    }

    public function verifyChallenge(Request $request, TwoFactorService $twoFactor)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if (!$user || $user->hasRole('admin') || !$user->hasTwoFactorEnabled()) {
            abort(403);
        }

        $inputCode  = (string) $request->input('code');
        $verifiedBy = null;

        if (preg_match('/^\d{6}$/', $inputCode) === 1) {
            if ($twoFactor->verifyCode((string) $user->two_factor_secret, $inputCode)) {
                $verifiedBy = 'totp';
            }
        } else {
            $existingCodes  = (array) ($user->two_factor_recovery_codes ?? []);
            $remainingCodes = $twoFactor->consumeRecoveryCode($existingCodes, $inputCode);

            if ($remainingCodes !== null) {
                $user->forceFill(['two_factor_recovery_codes' => $remainingCodes])->save();
                $verifiedBy = 'recovery_code';
            }
        }

        if ($verifiedBy === null) {
            return back()->withErrors(['code' => 'Invalid authenticator or recovery code. Please try again.']);
        }

        $request->session()->put('2fa_verified_user_id', (int) $user->id);
        $request->session()->put('2fa_verified_at', now()->toIso8601String());

        return redirect()->intended($this->dashboardRoute($user));
    }

    private function dashboardRoute(\App\Models\User $user): string
    {
        // Dual-role users choose their destination after passing 2FA.
        if ($user->hasRole('voter') && $user->hasRole('citizen')) {
            return route('portal-pick');
        }

        return match ($user->user_type) {
            'politician' => route('politician.dashboard'),
            'citizen'    => route('citizen.dashboard'),
            default      => route('voter.dashboard'),
        };
    }
}
