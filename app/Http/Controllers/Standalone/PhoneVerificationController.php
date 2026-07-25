<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Services\PhoneVerificationService;
use App\Services\UserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Standalone Phone Verification Controller
 *
 * Handles OTP verification during/after registration.
 */
class PhoneVerificationController extends Controller
{
    public function __construct(
        private readonly PhoneVerificationService $phoneService,
        private readonly UserRoleService $roleService,
    ) {}

    public function showVerifyPhone(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->phone_verified_at) {
            return redirect($this->roleService->dashboardRouteFor($user));
        }

        return view('standalone.auth.verify-phone', ['user' => $user]);
    }

    public function verifyPhone(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return back()->withErrors(['code' => 'Not authenticated.']);
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $verified = $this->phoneService->verifyCode($user->phone, $request->code, $user);

        if ($verified) {
            return redirect($this->roleService->dashboardRouteFor($user))
                ->with('success', 'Phone number verified successfully!');
        }

        return back()->withErrors(['code' => 'Invalid or expired verification code. Please try again.']);
    }

    public function resendPhoneCode(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user || $user->phone_verified_at) {
            return back()->withErrors(['phone' => 'Phone already verified.']);
        }

        $sent = $this->phoneService->sendVerificationCode($user->phone, $user);

        if ($sent) {
            return back()->with('success', 'Verification code resent to your phone.');
        }

        return back()->withErrors(['phone' => 'Failed to send verification code. Please try again later.']);
    }
}
