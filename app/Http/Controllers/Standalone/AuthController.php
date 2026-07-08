<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use App\Mail\AdminNewUserNotificationMail;
use App\Mail\WelcomeMail;
use App\Models\AdminSecurityAuditLog;
use App\Models\ReferralVisit;
use App\Services\AdminTwoFactorService;
use App\Services\PlatformSettingsService;
use App\Services\PhoneVerificationService;
use App\Services\RegistrationSecurityService;
use App\Services\UnclaimedPoliticianProfileService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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
        // Dual-role: voter who has also added a Citizen profile → portal picker.
        if ($user->hasRole('voter') && $user->hasRole('citizen')) {
            return route('portal-pick');
        }

        if ($user->hasRole('admin')) {
            return route('admin.dashboard');
        }

        if ($user->hasRole('politician')) {
            return route('politician.dashboard');
        }

        if ($user->hasRole('citizen')) {
            return route('citizen.dashboard');
        }

        if ($user->hasRole('voter')) {
            return route('voter.dashboard');
        }

        return match ($user->user_type) {
            'admin'      => route('admin.dashboard'),
            'politician' => route('politician.dashboard'),
            'citizen'    => route('citizen.dashboard'),
            default      => route('voter.dashboard'),
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
            $request->session()->forget([
                'admin_2fa_verified_user_id',
                'admin_2fa_verified_at',
            ]);
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function showAdminTwoFactorChallenge(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            abort(403);
        }

        $isEnforced = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$isEnforced) {
            return redirect()->route('admin.dashboard');
        }

        if (!$user->hasAdminTwoFactorEnabled()) {
            return redirect()->route('admin.2fa.setup')
                ->with('warning', 'Two-factor authentication is required before continuing.');
        }

        return view('standalone.auth.admin-2fa-challenge');
    }

    public function verifyAdminTwoFactorChallenge(Request $request, AdminTwoFactorService $twoFactorService)
    {
        $request->validate([
            'code' => ['required', 'string', 'max:32'],
        ]);

        $user = $request->user();

        if (!$user || !$user->hasRole('admin') || !$user->hasAdminTwoFactorEnabled()) {
            abort(403);
        }

        $inputCode = (string) $request->input('code');
        $verifiedBy = null;

        if (preg_match('/^\d{6}$/', $inputCode) === 1) {
            if ($twoFactorService->verifyCode((string) $user->admin_two_factor_secret, $inputCode)) {
                $verifiedBy = 'totp';
            }
        } else {
            $existingCodes = (array) ($user->admin_two_factor_recovery_codes ?? []);
            $remainingCodes = $twoFactorService->consumeRecoveryCode($existingCodes, $inputCode);

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

        return redirect()->route('admin.dashboard')->with('success', 'Two-factor verification complete.');
    }

    // -------------------------------------------------------------------------
    // Registration Closed — Mailing List
    // -------------------------------------------------------------------------

    public function showRegisterClosed()
    {
        return view('standalone.auth.register-closed');
    }

    public function storeMailingListSubscriber(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($request->email));

        \Illuminate\Support\Facades\DB::table('mailing_list_subscribers')->updateOrInsert(
            ['email' => $email],
            ['source' => 'register_closed', 'updated_at' => now(), 'created_at' => now()]
        );

        // Sync to Mailgun mailing list if configured
        $this->addToMailgunMailingList($email);

        return back()->with('mailing_list_success', "You're on the list! We'll email {$email} as soon as registration opens.");
    }

    /**
     * Add an email address to the configured Mailgun mailing list via the Members API.
     * Silently logs and returns on any failure — never breaks the user-facing flow.
     */
    private function addToMailgunMailingList(string $email): void
    {
        $listAddress = config('services.mailgun.mailing_list');
        $apiKey      = config('services.mailgun.secret');
        $endpoint    = config('services.mailgun.endpoint', 'api.mailgun.net');

        if (! $listAddress || ! $apiKey) {
            return; // Not configured — skip silently
        }

        try {
            $response = \Illuminate\Support\Facades\Http::withBasicAuth('api', $apiKey)
                ->asForm()
                ->post("https://{$endpoint}/v3/lists/{$listAddress}/members", [
                    'address'    => $email,
                    'subscribed' => 'yes',
                    'upsert'     => 'yes', // update if already exists
                ]);

            if (! $response->successful()) {
                \Illuminate\Support\Facades\Log::warning('MailingList: Mailgun Members API error', [
                    'email'  => $email,
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('MailingList: Mailgun Members API exception', [
                'email' => $email,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Registration — Role Chooser
    // -------------------------------------------------------------------------

    public function showRegisterChoose(Request $request)
    {
        if (! filter_var(PlatformSettingsService::get('registration_open', null, true), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('register.closed');
        }

        return view('standalone.auth.register-choose', [
            'referralCode' => $this->resolveIncomingReferralCode($request),
        ]);
    }

    // -------------------------------------------------------------------------
    // Politician Registration
    // -------------------------------------------------------------------------

    public function showRegisterPolitician(Request $request)
    {
        if (! filter_var(PlatformSettingsService::get('registration_open', null, true), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('register.closed');
        }

        return view('standalone.auth.register-politician', [
            'referralCode' => $this->resolveIncomingReferralCode($request),
        ]);
    }

    public function registerPolitician(Request $request)
    {
        if (! filter_var(PlatformSettingsService::get('registration_open', null, true), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('register.closed');
        }

        $request->validate([
            'first_name'       => ['required', 'string', 'max:255'],
            'last_name'        => ['required', 'string', 'max:255'],
            'email'            => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'         => ['required', 'confirmed', Rules\Password::defaults()],
            'phone'            => ['required', 'string', 'max:20', 'unique:users,phone'],
            'political_office' => ['required', 'string', 'max:255'],
            'party'            => ['required', 'string', 'max:100'],
            'governance_level' => ['required', 'string', 'max:50'],
            'state'            => ['required', 'string', 'size:2'],
            'city'             => ['required', 'string', 'max:100'],
            'referral_code'    => ['nullable', 'string', 'max:20'],
            'terms'            => ['accepted'],
        ]);

        // ── Pre-registration security checks (IP, rate limit, KYC duplicate) ──
        app(RegistrationSecurityService::class)->checkOrFail($request, $request->email);

        $user = User::create([
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'phone'           => $request->phone,
            'city'            => $request->city,
            'state'           => $request->state,
            'platform'        => 'standalone',
            'user_type'       => 'politician',
            'registration_ip' => $request->ip(),
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

        // Resolve referral code — could belong to a voter OR a politician
        $referredByVoterId      = null;
        $referredByPoliticianId = null;
        $refCode = $this->resolveIncomingReferralCode($request);
        if ($refCode) {
            $voterReferrer = Voter::where('referral_code', $refCode)->first();
            if ($voterReferrer) {
                $referredByVoterId = $voterReferrer->id;
            } else {
                $politicianReferrer = \App\Models\Politician::where('referral_code', $refCode)->first();
                $referredByPoliticianId = $politicianReferrer?->id;
            }
        }

        // Normalize keys to match the politicians table
        $politicianPayload = [
            'full_name'                 => trim($request->first_name . ' ' . $request->last_name),
            'political_office'          => $politicianData['political_office'] ?? null,
            'party_affiliation'         => $politicianData['party'] ?? null,
            'governance_level'          => $politicianData['governance_level'] ?? null,
            'state'                     => $politicianData['state'] ?? null,
            'city'                      => $politicianData['city'] ?? null,
            'referred_by_voter_id'      => $referredByVoterId,
            'referred_by_politician_id' => $referredByPoliticianId,
        ];

        app(UnclaimedPoliticianProfileService::class)
            ->claimOrCreate($user, $politicianPayload);

        $this->markReferralConversion($request, $refCode, $user);

        // ── Trigger phone verification OTP ────────────────────────────────────
        try {
            app(PhoneVerificationService::class)->sendVerificationCode($user->phone, $user);
        } catch (\Exception $e) {
            Log::error('Failed to send phone verification code for politician', [
                'user_id' => $user->id,
                'phone'   => substr($user->phone, -4),
                'error'   => $e->getMessage(),
            ]);
        }

        event(new Registered($user));

        // Send welcome email (non-fatal if SMTP not yet configured)
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $e) {
            Log::error('WelcomeMail failed for politician', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }

        // Notify all admins of the new politician signup
        try {
            $user->loadMissing('politician');
            $admins = User::where('user_type', 'admin')->whereNotNull('email')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new AdminNewUserNotificationMail($user));
            }
        } catch (\Exception $e) {
            Log::error('AdminNewUserNotificationMail failed for politician', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        return redirect()->route('phone.verify');
    }

    // -------------------------------------------------------------------------    // Citizen Registration
    // -------------------------------------------------------------------------

    public function showRegisterCitizen(Request $request)
    {
        if (! filter_var(PlatformSettingsService::get('registration_open', null, true), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('register.closed');
        }

        // Already logged in — route to the correct in-account upgrade path.
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->hasRole('citizen')) {
                return redirect()->route('citizen.dashboard');
            }
            return redirect()->route('add-citizen-profile')
                ->with('info', 'You already have an account. Use this form to add a Citizen advertiser profile to it.');
        }

        return view('standalone.auth.register-citizen', [
            'referralCode' => $this->resolveIncomingReferralCode($request),
        ]);
    }

    public function registerCitizen(Request $request)
    {
        if (! filter_var(PlatformSettingsService::get('registration_open', null, true), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('register.closed');
        }

        // Logged-in user hit the public form — redirect before validation fires.
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->hasRole('citizen')) {
                return redirect()->route('citizen.dashboard');
            }
            return redirect()->route('add-citizen-profile')
                ->with('info', 'You already have an account. Use this form to add a Citizen advertiser profile to it.');
        }

        $request->validate([
            'first_name'          => ['required', 'string', 'max:255'],
            'last_name'           => ['required', 'string', 'max:255'],
            'email'               => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'            => ['required', 'confirmed', Rules\Password::defaults()],
            'phone'               => ['required', 'string', 'max:20', 'unique:users,phone'],
            'business_name'       => ['nullable', 'string', 'max:255'],
            'state'               => ['required', 'string', 'size:2'],
            'city'                => ['required', 'string', 'max:100'],
            'address_line_1'      => ['required', 'string', 'max:255'],
            'address_line_2'      => ['nullable', 'string', 'max:255'],
            'zip'                 => ['required', 'string', 'max:10', 'regex:/^\d{5}(-\d{4})?$/'],
            'referral_code'       => ['nullable', 'string', 'max:20'],
            'terms'               => ['accepted'],
        ]);

        // ── Pre-registration security checks (IP, rate limit, KYC duplicate) ──
        app(RegistrationSecurityService::class)->checkOrFail($request, $request->email);

        $user = User::create([
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'phone'           => $request->phone,
            'city'            => $request->city,
            'state'           => $request->state,
            'platform'        => 'standalone',
            'user_type'       => 'citizen',
            'registration_ip' => $request->ip(),
        ]);

        $user->assignRole('citizen');

        // Resolve referral code — could belong to a voter OR a politician
        $referredByVoterId      = null;
        $referredByPoliticianId = null;
        $refCode = $this->resolveIncomingReferralCode($request);
        if ($refCode) {
            $voterReferrer = Voter::where('referral_code', $refCode)->first();
            if ($voterReferrer) {
                $referredByVoterId = $voterReferrer->id;
            } else {
                $politicianReferrer = Politician::where('referral_code', $refCode)->first();
                $referredByPoliticianId = $politicianReferrer?->id;
            }
        }

        $citizenPayload = [
            'user_id'                   => $user->id,
            'full_name'                 => trim($request->first_name . ' ' . $request->last_name),
            'business_name'             => $request->business_name,
            'state'                     => $request->state,
            'city'                      => $request->city,
            'address_line_1'            => $request->address_line_1,
            'address_line_2'            => $request->address_line_2,
            'zip'                       => $request->zip,
            'referred_by_voter_id'      => $referredByVoterId,
            'referred_by_politician_id' => $referredByPoliticianId,
        ];

        Citizen::create($citizenPayload);

        $this->markReferralConversion($request, $refCode, $user);

        // ── Trigger phone verification OTP ────────────────────────────────────
        try {
            app(PhoneVerificationService::class)->sendVerificationCode($user->phone, $user);
        } catch (\Exception $e) {
            Log::error('Failed to send phone verification code for citizen', [
                'user_id' => $user->id,
                'phone'   => substr($user->phone, -4),
                'error'   => $e->getMessage(),
            ]);
        }

        event(new Registered($user));

        // Send welcome email (non-fatal if SMTP not yet configured)
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $e) {
            Log::error('WelcomeMail failed for citizen', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }

        // Notify all admins of the new citizen signup
        try {
            $user->loadMissing('citizen');
            $admins = User::where('user_type', 'admin')->whereNotNull('email')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new AdminNewUserNotificationMail($user));
            }
        } catch (\Exception $e) {
            Log::error('AdminNewUserNotificationMail failed for citizen', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        return redirect()->route('phone.verify');
    }

    // -------------------------------------------------------------------------    // Voter Registration
    // -------------------------------------------------------------------------

    public function showRegisterVoter(Request $request)
    {
        if (! filter_var(PlatformSettingsService::get('registration_open', null, true), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('register.closed');
        }

        // Already logged in — send them to their dashboard.
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->hasRole('voter')) {
                return redirect()->route('voter.dashboard');
            }
            // Has an account but not yet a voter profile — not a supported upgrade path
            // from the public registration form; send to dashboard and let them choose.
            return redirect()->route('dashboard');
        }

        return view('standalone.auth.register-voter', [
            'referralCode' => $this->resolveIncomingReferralCode($request),
        ]);
    }

    public function registerVoter(Request $request)
    {
        if (! filter_var(PlatformSettingsService::get('registration_open', null, true), FILTER_VALIDATE_BOOLEAN)) {
            return redirect()->route('register.closed');
        }

        // Logged-in user hit the public voter form — redirect before validation fires.
        if (auth()->check()) {
            $user = auth()->user();
            if ($user->hasRole('voter')) {
                return redirect()->route('voter.dashboard');
            }
            return redirect()->route('dashboard');
        }

        $request->validate([
            'first_name'    => ['required', 'string', 'max:255'],
            'last_name'     => ['required', 'string', 'max:255'],
            'email'         => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'      => ['required', 'confirmed', Rules\Password::defaults()],
            'phone'         => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'state'         => ['nullable', 'string', 'size:2'],
            'zip_code'      => ['required', 'string', 'max:10', 'regex:/^\d{5}(-\d{4})?$/'],
            'referral_code'        => ['nullable', 'string', 'max:20'],
            'is_registered_voter' => ['nullable', 'boolean'],
            'terms'               => ['accepted'],
        ]);

        // ── Pre-registration security checks (IP, rate limit, KYC duplicate) ──
        app(RegistrationSecurityService::class)->checkOrFail($request, $request->email);

        $user = User::create([
            'first_name'      => $request->first_name,
            'last_name'       => $request->last_name,
            'email'           => $request->email,
            'password'        => Hash::make($request->password),
            'phone'           => $request->phone,
            'platform'        => 'standalone',
            'user_type'       => 'voter',
            'registration_ip' => $request->ip(),
        ]);

        $user->assignRole('voter');

        // Resolve referral code — could belong to a voter OR a politician
        $referredByVoterId      = null;
        $referredByPoliticianId = null;
        $refCode = $this->resolveIncomingReferralCode($request);
        if ($refCode) {
            $voterReferrer = Voter::where('referral_code', $refCode)->first();
            if ($voterReferrer) {
                $referredByVoterId = $voterReferrer->id;
            } else {
                $politicianReferrer = \App\Models\Politician::where('referral_code', $refCode)->first();
                $referredByPoliticianId = $politicianReferrer?->id;
            }
        }

        $voterData = $request->only([
            'state',
            'zip_code',
            'phone',
            'referral_code',
        ]);

        $voterPayload = [
            'full_name'                 => trim($request->first_name . ' ' . $request->last_name),
            'email'                     => $user->email,
            'phone'                     => $voterData['phone'] ?? $request->input('phone'),
            'state'                     => $voterData['state'] ?? null,
            'zip_code'                  => $voterData['zip_code'] ?? null,
            'referred_by_voter_id'      => $referredByVoterId,
            'referred_by_politician_id' => $referredByPoliticianId,
            'wallet_balance'            => 0,
            'trust_score'               => 100,
            'is_active'                 => true,
            'is_verified'               => false,
            'is_registered_voter'       => null,
        ];

        if ($request->input('is_registered_voter') === '1') {
            $voterPayload['is_registered_voter'] = true;
        } elseif ($request->input('is_registered_voter') === '0') {
            $voterPayload['is_registered_voter'] = false;
        }

        // Search by email so that any orphaned voter row (user_id = NULL) created
        // during a failed previous registration attempt is adopted rather than
        // leaving a broken duplicate. Always write user_id into the record.
        \App\Models\Voter::updateOrCreate(
            ['email' => $user->email],
            array_merge($voterPayload, ['user_id' => $user->id])
        );

        $this->markReferralConversion($request, $refCode, $user);

        // ── Trigger phone verification OTP (if phone provided) ────────────────
        if ($user->phone) {
            try {
                app(PhoneVerificationService::class)->sendVerificationCode($user->phone, $user);
            } catch (\Exception $e) {
                Log::error('Failed to send phone verification code for voter', [
                    'user_id' => $user->id,
                    'phone'   => substr($user->phone, -4),
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        event(new Registered($user));

        // Send welcome email (non-fatal if SMTP not yet configured)
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $e) {
            Log::error('WelcomeMail failed for voter', [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }

        // Notify all admins of the new voter signup
        try {
            $admins = User::where('user_type', 'admin')->whereNotNull('email')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new AdminNewUserNotificationMail($user));
            }
        } catch (\Exception $e) {
            Log::error('AdminNewUserNotificationMail failed for voter', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        Auth::login($user);

        return $user->phone
            ? redirect()->route('phone.verify')
            : redirect()->route('verification.notice');
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
    // Phone Verification (OTP)
    // -------------------------------------------------------------------------

    /**
     * Show phone verification form after registration.
     * User must submit the OTP code they received via SMS.
     */
    public function showVerifyPhone(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->phone_verified_at) {
            return redirect($this->roleRedirect($user));
        }

        return view('standalone.auth.verify-phone', ['user' => $user]);
    }

    /**
     * Verify the OTP code submitted by the user.
     */
    public function verifyPhone(Request $request, PhoneVerificationService $phoneService)
    {
        $user = $request->user();
        if (!$user) {
            return back()->withErrors(['code' => 'Not authenticated.']);
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^\d{6}$/'],
        ]);

        $verified = $phoneService->verifyCode($user->phone, $request->code, $user);

        if ($verified) {
            return redirect($this->roleRedirect($user))
                ->with('success', 'Phone number verified successfully!');
        }

        return back()->withErrors(['code' => 'Invalid or expired verification code. Please try again.']);
    }

    /**
     * Resend verification code to phone.
     */
    public function resendPhoneCode(Request $request, PhoneVerificationService $phoneService)
    {
        $user = $request->user();
        if (!$user || $user->phone_verified_at) {
            return back()->withErrors(['phone' => 'Phone already verified.']);
        }

        $sent = $phoneService->sendVerificationCode($user->phone, $user);

        if ($sent) {
            return back()->with('success', 'Verification code resent to your phone.');
        }

        return back()->withErrors(['phone' => 'Failed to send verification code. Please try again later.']);
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

    private function resolveIncomingReferralCode(Request $request): ?string
    {
        $refCode = $request->input('referral_code')
            ?: $request->query('ref')
            ?: $request->query('referral_code')
            ?: $request->session()->get('referral.code')
            ?: $request->cookie('u9_referral_code');

        if (!is_string($refCode) || trim($refCode) === '') {
            return null;
        }

        return strtoupper(trim($refCode));
    }

    private function markReferralConversion(Request $request, ?string $refCode, User $user): void
    {
        if (!is_string($refCode) || trim($refCode) === '') {
            return;
        }

        $sessionId = $request->session()->getId();

        if (!is_string($sessionId) || $sessionId === '') {
            return;
        }

        $visit = ReferralVisit::where('referral_code', strtoupper(trim($refCode)))
            ->where('session_id', $sessionId)
            ->latest('id')
            ->first();

        if (!$visit) {
            $visit = ReferralVisit::where('referral_code', strtoupper(trim($refCode)))
                ->whereNull('converted_user_id')
                ->latest('id')
                ->first();
        }

        if (!$visit || $visit->converted_user_id) {
            return;
        }

        $visit->update([
            'converted_user_id' => $user->id,
            'converted_user_type' => $user->user_type,
            'converted_at' => now(),
        ]);
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
