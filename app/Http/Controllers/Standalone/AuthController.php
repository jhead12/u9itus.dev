<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use App\Mail\AdminNewUserNotificationMail;
use App\Mail\WelcomeMail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;

/**
 * Standalone Authentication Controller
 *
 * Handles registration, login, and password reset for the standalone platform.
 * Separate registration flows exist for politicians and voters.
 * Admin users have their own dedicated login portal.
 */
class AuthController extends Controller
{
    // -------------------------------------------------------------------------
    // Shared Login
    // -------------------------------------------------------------------------

    public function showLogin()
    {
        return view('standalone.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            $request->session()->regenerate();
            return redirect()->intended($this->roleRedirect(Auth::user()));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Resolve the correct post-login destination for a given user.
     * Checks Spatie roles first, then falls back to the user_type column.
     */
    private function roleRedirect(\App\Models\User $user): string
    {
        if ($user->hasRole('admin'))      return route('admin.dashboard');
        if ($user->hasRole('politician')) return route('politician.dashboard');
        if ($user->hasRole('voter'))      return route('voter.dashboard');

        // Fallback: use user_type column in case the role row is missing
        return match($user->user_type) {
            'admin'      => route('admin.dashboard'),
            'politician' => route('politician.dashboard'),
            'voter'      => route('voter.dashboard'),
            default      => route('dashboard'),
        };
    }

    // -------------------------------------------------------------------------
    // Admin Login Portal
    // -------------------------------------------------------------------------

    public function showAdminLogin()
    {
        return view('standalone.auth.admin-login');
    }

    public function adminLogin(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($request->only('email', 'password'), false)) {
            $user = Auth::user();

            if (! $user->hasRole('admin')) {
                Auth::logout();
                return back()->withErrors([
                    'email' => 'Access denied. This portal is for administrators only.',
                ])->onlyInput('email');
            }

            $request->session()->regenerate();
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    // -------------------------------------------------------------------------
    // Registration — Role Chooser
    // -------------------------------------------------------------------------

    public function showRegisterChoose()
    {
        return view('standalone.auth.register-choose');
    }

    // -------------------------------------------------------------------------
    // Politician Registration
    // -------------------------------------------------------------------------

    public function showRegisterPolitician()
    {
        return view('standalone.auth.register-politician');
    }

    public function registerPolitician(Request $request)
    {
        $request->validate([
            'name'             => ['required', 'string', 'max:255'],
            'email'            => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'         => ['required', 'confirmed', Rules\Password::defaults()],
            'phone'            => ['required', 'string', 'max:20'],
            'political_office' => ['required', 'string', 'max:255'],
            'party'            => ['required', 'string', 'max:100'],
            'governance_level' => ['required', 'string', 'max:50'],
            'state'            => ['required', 'string', 'size:2'],
            'city'             => ['required', 'string', 'max:100'],
            'referral_code'    => ['nullable', 'string', 'max:20'],
            'terms'            => ['accepted'],
        ]);

        $nameParts = explode(' ', trim($request->name), 2);
        $firstName = $nameParts[0];
        $lastName  = $nameParts[1] ?? '';

        $user = User::create([
            'name'       => $request->name,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'email'      => $request->email,
            'password'   => Hash::make($request->password),
            'phone'      => $request->phone,
            'city'       => $request->city,
            'state'      => $request->state,
            'platform'   => 'standalone',
            'user_type'  => 'politician',
        ]);

        $user->assignRole('politician');

        // Create the politician profile record (firstOrCreate prevents duplicates on form retry)
        $politicianData = $request->only([
            'political_office',
            'party',
            'governance_level',
            'state',
            'city',
        ]);

        // Resolve referrer voter if a referral code was supplied
        $referredByVoterId = null;
        $refCode = $request->input('referral_code') ?: $request->query('ref');
        if ($refCode) {
            $referrer = Voter::where('referral_code', $refCode)->first();
            $referredByVoterId = $referrer?->id;
        }

        // Normalize keys to match the politicians table
        $politicianPayload = [
            'full_name'             => $request->input('name'),
            'political_office'      => $politicianData['political_office'] ?? null,
            'party_affiliation'     => $politicianData['party'] ?? null,
            'governance_level'      => $politicianData['governance_level'] ?? null,
            'state'                 => $politicianData['state'] ?? null,
            'city'                  => $politicianData['city'] ?? null,
            'referred_by_voter_id'  => $referredByVoterId,
        ];

        // Use updateOrCreate to ensure fields are written deterministically
        \App\Models\Politician::updateOrCreate(
            ['user_id' => $user->id],
            $politicianPayload
        );

        event(new Registered($user));

        // Send welcome email (non-fatal if SMTP not yet configured)
        try {
            Mail::to($user->email)->queue(new WelcomeMail($user));
        } catch (\Exception) {
            // silently skip — email config may not be set up yet
        }

        // Notify all admins of the new politician signup
        try {
            $user->loadMissing('politician');
            $admins = User::where('user_type', 'admin')->whereNotNull('email')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(new AdminNewUserNotificationMail($user));
            }
        } catch (\Exception) {
            // Non-fatal
        }

        Auth::login($user);

        return redirect()->route('verification.notice');
    }

    // -------------------------------------------------------------------------
    // Voter Registration
    // -------------------------------------------------------------------------

    public function showRegisterVoter()
    {
        return view('standalone.auth.register-voter');
    }

    public function registerVoter(Request $request)
    {
        $request->validate([
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'      => ['required', 'confirmed', Rules\Password::defaults()],
            'phone'         => ['nullable', 'string', 'max:20'],
            'state'         => ['nullable', 'string', 'size:2'],
            'zip_code'      => ['nullable', 'string', 'max:10'],
            'referral_code' => ['nullable', 'string', 'max:20'],
            'terms'         => ['accepted'],
        ]);

        $user = User::create([
            'name'      => $request->name,
            'email'     => $request->email,
            'password'  => Hash::make($request->password),
            'phone'     => $request->phone,
            'platform'  => 'standalone',
            'user_type' => 'voter',
        ]);

        $user->assignRole('voter');

        // Resolve referral voter if a code was provided
        $referredByVoterId = null;
        if ($request->filled('referral_code')) {
            $referrer = Voter::where('referral_code', $request->referral_code)->first();
            $referredByVoterId = $referrer?->id;
        }

        $voterData = $request->only([
            'state',
            'zip_code',
            'phone',
            'referral_code',
        ]);

        $voterPayload = [
            'full_name'            => $request->input('name'),
            'email'                => $user->email,
            'phone'                => $voterData['phone'] ?? $request->input('phone'),
            'state'                => $voterData['state'] ?? null,
            'zip_code'             => $voterData['zip_code'] ?? null,
            'referred_by_voter_id' => $referredByVoterId,
            'wallet_balance'       => 0,
            'trust_score'          => 100,
            'is_active'            => true,
            'is_verified'          => false,
        ];

        // Search by email so that any orphaned voter row (user_id = NULL) created
        // during a failed previous registration attempt is adopted rather than
        // leaving a broken duplicate. Always write user_id into the record.
        \App\Models\Voter::updateOrCreate(
            ['email' => $user->email],
            array_merge($voterPayload, ['user_id' => $user->id])
        );

        event(new Registered($user));

        // Send welcome email (non-fatal if SMTP not yet configured)
        try {
            Mail::to($user->email)->queue(new WelcomeMail($user));
        } catch (\Exception) {
            // silently skip — email config may not be set up yet
        }

        // Notify all admins of the new voter signup
        try {
            $admins = User::where('user_type', 'admin')->whereNotNull('email')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(new AdminNewUserNotificationMail($user));
            }
        } catch (\Exception) {
            // Non-fatal
        }

        Auth::login($user);

        return redirect()->route('verification.notice');
    }

    // -------------------------------------------------------------------------
    // Logout
    // -------------------------------------------------------------------------

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    // -------------------------------------------------------------------------
    // Password Reset
    // -------------------------------------------------------------------------

    public function showForgotPassword()
    {
        return view('standalone.auth.forgot-password');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with(['status' => __($status)])
            : back()->withErrors(['email' => __($status)]);
    }

    public function showResetPassword(string $token)
    {
        return view('standalone.auth.reset-password', ['token' => $token]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'    => 'required',
            'email'    => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => [__($status)]]);
    }

    // -------------------------------------------------------------------------
    // Email Verification
    // -------------------------------------------------------------------------

    public function showVerifyEmail()
    {
        return view('standalone.auth.verify-email');
    }

    public function verifyEmail(Request $request)
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

        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard') . '?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        return redirect()->intended(route('dashboard') . '?verified=1');
    }

    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard'));
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-link-sent');
    }
}
