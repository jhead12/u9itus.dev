<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Services\UserRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Standalone Login Controller
 *
 * Handles shared login, the admin login portal, and logout for the
 * standalone platform.
 */
class LoginController extends Controller
{
    public function __construct(
        private readonly UserRoleService $roleService,
    ) {}

    public function showLogin()
    {
        return view('standalone.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended($this->roleService->dashboardRouteFor(Auth::user()));
        }

        return back()
            ->withErrors(['email' => 'The provided credentials do not match our records.'])
            ->onlyInput('email');
    }

    public function showAdminLogin()
    {
        return view('standalone.auth.admin-login');
    }

    public function adminLogin(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), false)) {
            $user = Auth::user();

            if (! $this->roleService->hasRole($user, 'admin')) {
                Auth::logout();
                return back()
                    ->withErrors(['email' => 'Access denied. This portal is for administrators only.'])
                    ->onlyInput('email');
            }

            $request->session()->regenerate();
            $request->session()->forget([
                'admin_2fa_verified_user_id',
                'admin_2fa_verified_at',
            ]);

            return redirect()->route('admin.dashboard');
        }

        return back()
            ->withErrors(['email' => 'The provided credentials do not match our records.'])
            ->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
