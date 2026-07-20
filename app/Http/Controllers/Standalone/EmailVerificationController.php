<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Services\UserRoleService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Standalone Email Verification Controller
 */
class EmailVerificationController extends Controller
{
    public function __construct(
        private readonly UserRoleService $roleService,
    ) {}

    public function showVerifyEmail()
    {
        return view('standalone.auth.verify-email');
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403, 'Invalid or expired verification link.');
        }

        if (! hash_equals(
            (string) sha1($request->user()->getEmailForVerification()),
            (string) $request->route('hash')
        )) {
            abort(403, 'Invalid verification link.');
        }

        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($this->roleService->dashboardRouteFor($user) . '?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->intended($this->roleService->dashboardRouteFor($user) . '?verified=1');
    }

    public function resendVerification(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return redirect()->intended($this->roleService->dashboardRouteFor($user));
        }

        $user->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
