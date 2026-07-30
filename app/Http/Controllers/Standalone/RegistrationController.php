<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Http\Middleware\CaptureEarlyBankReferral;
use App\Jobs\GeocodeCitizenAddress;
use App\Mail\AdminNewUserNotificationMail;
use App\Mail\WelcomeMail;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use App\Services\EarlyBankPrefillService;
use App\Services\EarlyBankWebhookService;
use App\Services\MailingListService;
use App\Services\PhoneVerificationService;
use App\Services\PlatformSettingsService;
use App\Services\DistrictLookupService;
use App\Services\ReferralService;
use App\Services\RegistrationSecurityService;
use App\Services\UnclaimedPoliticianProfileService;
use App\Services\UserRoleService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;

/**
 * Standalone Registration Controller
 *
 * Handles role-specific registration flows (politician, voter, citizen),
 * the registration-closed mailing list capture, and post-registration
 * notifications / referrals.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private readonly ReferralService $referralService,
        private readonly MailingListService $mailingListService,
        private readonly PhoneVerificationService $phoneService,
        private readonly UserRoleService $roleService,
        private readonly EarlyBankPrefillService $earlyBankPrefill,
        private readonly DistrictLookupService $districtLookup,
    ) {}

    // -------------------------------------------------------------------------
    // Registration Open Gate
    // -------------------------------------------------------------------------

    private function registrationIsOpen(): bool
    {
        return filter_var(
            PlatformSettingsService::get('registration_open', null, true),
            FILTER_VALIDATE_BOOLEAN
        );
    }

    private function redirectIfRegistrationClosed(): ?RedirectResponse
    {
        if ($this->registrationIsOpen()) {
            return null;
        }

        return redirect()->route('register.closed');
    }

    // -------------------------------------------------------------------------
    // Role Chooser
    // -------------------------------------------------------------------------

    public function showRegisterChoose(Request $request)
    {
        if ($redirect = $this->redirectIfRegistrationClosed()) {
            return $redirect;
        }

        return view('standalone.auth.register-choose', [
            'referralCode' => $this->referralService->resolveIncomingReferralCode($request),
        ]);
    }

    // -------------------------------------------------------------------------
    // Registration Closed — Mailing List
    // -------------------------------------------------------------------------

    public function showRegisterClosed()
    {
        return view('standalone.auth.register-closed');
    }

    public function storeMailingListSubscriber(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($request->email));

        \Illuminate\Support\Facades\DB::table('mailing_list_subscribers')->updateOrInsert(
            ['email' => $email],
            ['source' => 'register_closed', 'updated_at' => now(), 'created_at' => now()]
        );

        $this->mailingListService->subscribe($email, 'register_closed');

        return back()->with('mailing_list_success', "You're on the list! We'll email {$email} as soon as registration opens.");
    }

    // -------------------------------------------------------------------------
    // Politician Registration
    // -------------------------------------------------------------------------

    public function showRegisterPolitician(Request $request)
    {
        if ($redirect = $this->redirectIfRegistrationClosed()) {
            return $redirect;
        }

        return view('standalone.auth.register-politician', [
            'referralCode' => $this->referralService->resolveIncomingReferralCode($request),
        ]);
    }

    public function registerPolitician(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfRegistrationClosed()) {
            return $redirect;
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

        $refCode = $this->referralService->resolveIncomingReferralCode($request);
        ['referred_by_voter_id' => $referredByVoterId, 'referred_by_politician_id' => $referredByPoliticianId] =
            $this->referralService->resolveReferrerIds($refCode);

        $politicianPayload = [
            'full_name'                 => trim($request->first_name . ' ' . $request->last_name),
            'political_office'          => $request->political_office,
            'party_affiliation'         => $request->party,
            'governance_level'          => $request->governance_level,
            'state'                     => $request->state,
            'city'                      => $request->city,
            'referred_by_voter_id'      => $referredByVoterId,
            'referred_by_politician_id' => $referredByPoliticianId,
        ];

        app(UnclaimedPoliticianProfileService::class)->claimOrCreate($user, $politicianPayload);

        $this->referralService->markReferralConversion($request, $refCode, $user);
        $this->sendPhoneVerification($user->phone, $user, 'politician');

        event(new Registered($user));
        $this->sendWelcomeEmail($user, 'politician');
        $this->notifyAdmins($user);

        Auth::login($user);

        return redirect()->route('phone.verify');
    }

    // -------------------------------------------------------------------------
    // Citizen Registration
    // -------------------------------------------------------------------------

    public function showRegisterCitizen(Request $request)
    {
        if ($redirect = $this->redirectIfRegistrationClosed()) {
            return $redirect;
        }

        if (auth()->check()) {
            $user = auth()->user();
            if ($this->roleService->hasRole($user, 'citizen')) {
                return redirect()->route('citizen.dashboard');
            }
            return redirect()->route('add-citizen-profile')
                ->with('info', 'You already have an account. Use this form to add a Citizen advertiser profile to it.');
        }

        return view('standalone.auth.register-citizen', [
            'referralCode' => $this->referralService->resolveIncomingReferralCode($request),
        ]);
    }

    public function registerCitizen(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfRegistrationClosed()) {
            return $redirect;
        }

        if (auth()->check()) {
            $user = auth()->user();
            if ($this->roleService->hasRole($user, 'citizen')) {
                return redirect()->route('citizen.dashboard');
            }
            return redirect()->route('add-citizen-profile')
                ->with('info', 'You already have an account. Use this form to add a Citizen advertiser profile to it.');
        }

        $request->validate([
            'first_name'     => ['required', 'string', 'max:255'],
            'last_name'      => ['required', 'string', 'max:255'],
            'email'          => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'       => ['required', 'confirmed', Rules\Password::defaults()],
            'phone'          => ['required', 'string', 'max:20', 'unique:users,phone'],
            'business_name'  => ['nullable', 'string', 'max:255'],
            'state'          => ['required', 'string', 'size:2'],
            'city'           => ['required', 'string', 'max:100'],
            'address_line_1' => ['required', 'string', 'max:255'],
            'address_line_2' => ['nullable', 'string', 'max:255'],
            'zip'            => ['required', 'string', 'max:10', 'regex:/^\d{5}(-\d{4})?$/'],
            'referral_code'  => ['nullable', 'string', 'max:20'],
            'terms'          => ['accepted'],
        ]);

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

        $refCode = $this->referralService->resolveIncomingReferralCode($request);
        ['referred_by_voter_id' => $referredByVoterId, 'referred_by_politician_id' => $referredByPoliticianId] =
            $this->referralService->resolveReferrerIds($refCode);

        $citizen = Citizen::create([
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
        ]);

        GeocodeCitizenAddress::dispatch($citizen->id);

        $this->referralService->markReferralConversion($request, $refCode, $user);
        $this->sendPhoneVerification($user->phone, $user, 'citizen');

        event(new Registered($user));
        $this->sendWelcomeEmail($user, 'citizen');
        $this->notifyAdmins($user);

        Auth::login($user);

        return redirect()->route('phone.verify');
    }

    // -------------------------------------------------------------------------
    // Voter Registration
    // -------------------------------------------------------------------------

    public function showRegisterVoter(Request $request)
    {
        if ($redirect = $this->redirectIfRegistrationClosed()) {
            return $redirect;
        }

        // Guest-trial voters (see ProvisionGuestVoterSession) already carry the
        // voter role, but must still reach this form — registering while a
        // guest upgrades their existing session in place (see registerVoter()).
        if (auth()->check() && !auth()->user()->is_guest) {
            $user = auth()->user();
            if ($this->roleService->hasRole($user, 'voter')) {
                return redirect()->route('voter.dashboard');
            }
            return redirect()->route('dashboard');
        }

        return view('standalone.auth.register-voter', [
            'referralCode' => $this->referralService->resolveIncomingReferralCode($request),
            'prefill'      => $this->earlyBankPrefill->decode($request),
        ]);
    }

    public function registerVoter(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfRegistrationClosed()) {
            return $redirect;
        }

        $isGuestUpgrade = auth()->check() && auth()->user()->is_guest;

        if (auth()->check() && !$isGuestUpgrade) {
            $user = auth()->user();
            if ($this->roleService->hasRole($user, 'voter')) {
                return redirect()->route('voter.dashboard');
            }
            return redirect()->route('dashboard');
        }

        $request->validate([
            'first_name'          => ['required', 'string', 'max:255'],
            'last_name'           => ['required', 'string', 'max:255'],
            'email'               => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password'            => ['required', 'confirmed', Rules\Password::defaults()],
            'phone'               => ['nullable', 'string', 'max:20', 'unique:users,phone'],
            'state'               => ['nullable', 'string', 'size:2'],
            'zip_code'            => ['required', 'string', 'max:10', 'regex:/^\d{5}(-\d{4})?$/'],
            'referral_code'       => ['nullable', 'string', 'max:20'],
            'is_registered_voter' => ['nullable', 'boolean'],
            'terms'               => ['accepted'],
        ]);

        app(RegistrationSecurityService::class)->checkOrFail($request, $request->email);

        if ($isGuestUpgrade) {
            // Update the same User+Voter rows in place so every favorite/note
            // the guest already made survives the upgrade to a real account.
            $user = auth()->user();
            $user->update([
                'first_name'        => $request->first_name,
                'last_name'         => $request->last_name,
                'email'             => $request->email,
                // The guest's sentinel address was marked verified at
                // provisioning time purely so Laravel's `verified`
                // middleware would let them through with no real inbox —
                // that doesn't carry over to this newly-set real email.
                'email_verified_at' => null,
                'password'          => Hash::make($request->password),
                'phone'             => $request->phone,
                'registration_ip'   => $request->ip(),
                'is_guest'          => false,
                'guest_expires_at'  => null,
            ]);
        } else {
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
        }

        $refCode = $this->referralService->resolveIncomingReferralCode($request);
        ['referred_by_voter_id' => $referredByVoterId, 'referred_by_politician_id' => $referredByPoliticianId] =
            $this->referralService->resolveReferrerIds($refCode);

        $voterPayload = [
            'full_name'                 => trim($request->first_name . ' ' . $request->last_name),
            'email'                     => $user->email,
            'phone'                     => $request->phone,
            'state'                     => $request->state,
            'zip_code'                  => $request->zip_code,
            'congressional_district'    => $this->resolveDistrict($request->zip_code, $request->state),
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

        // Early-bank referral attribution
        $ebMemberId = $request->cookie(CaptureEarlyBankReferral::COOKIE_NAME);
        if (is_string($ebMemberId) && Str::isUuid($ebMemberId)) {
            $voterPayload['earlybank_member_id'] = $ebMemberId;
            $voterPayload['earlybank_linked_at']  = now();
        } else {
            $ebMemberId = null;
        }

        // A guest's existing Voter row still carries the sentinel guest email,
        // so match it by user_id rather than the just-set real email.
        $voterMatchKey = $isGuestUpgrade ? ['user_id' => $user->id] : ['email' => $user->email];

        $existingVoter = Voter::where($voterMatchKey)->first();
        if ($existingVoter && $existingVoter->earlybank_member_id !== null) {
            unset($voterPayload['earlybank_member_id'], $voterPayload['earlybank_linked_at']);
            $ebMemberId = null;
        }

        $voter = Voter::updateOrCreate(
            $voterMatchKey,
            array_merge($voterPayload, ['user_id' => $user->id])
        );

        if ($ebMemberId !== null) {
            app(EarlyBankWebhookService::class)->handleVoterRegistered($voter, $ebMemberId);
        }

        $this->referralService->markReferralConversion($request, $refCode, $user);

        if ($user->phone) {
            $this->sendPhoneVerification($user->phone, $user, 'voter');
        }

        event(new Registered($user));
        $this->sendWelcomeEmail($user, 'voter');
        $this->notifyAdmins($user);

        Auth::login($user);

        $destination = $user->phone
            ? redirect()->route('phone.verify')
            : redirect()->route('verification.notice');

        if ($ebMemberId !== null) {
            $destination->withCookie(Cookie::forget(CaptureEarlyBankReferral::COOKIE_NAME));
        }

        return $destination;
    }

    // -------------------------------------------------------------------------
    // Shared Helpers
    // -------------------------------------------------------------------------

    /**
     * Best-effort congressional district resolution at registration time.
     * Never blocks registration — DistrictLookupService already swallows its
     * own provider exceptions and returns null, but this is a safety net for
     * anything unexpected (e.g. a malformed zip/state combination).
     */
    private function resolveDistrict(?string $zipCode, ?string $state): ?string
    {
        if (! $zipCode) {
            return null;
        }

        try {
            $result = $this->districtLookup->lookup(trim("{$zipCode}, {$state}"));

            return $result['district_code'] ?? null;
        } catch (\Throwable $e) {
            Log::warning('RegistrationController: district lookup failed', [
                'zip_code' => $zipCode,
                'state'    => $state,
                'error'    => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function sendPhoneVerification(string $phone, User $user, string $context): void
    {
        try {
            $this->phoneService->sendVerificationCode($phone, $user);
        } catch (\Exception $e) {
            Log::error("Failed to send phone verification code for {$context}", [
                'user_id' => $user->id,
                'phone'   => substr($phone, -4),
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function sendWelcomeEmail(User $user, string $context): void
    {
        try {
            Mail::to($user->email)->send(new WelcomeMail($user));
        } catch (\Exception $e) {
            Log::error("WelcomeMail failed for {$context}", [
                'user_id' => $user->id,
                'email'   => $user->email,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    private function notifyAdmins(User $user): void
    {
        try {
            $user->loadMissing('politician', 'citizen', 'voter');
            $admins = User::where('user_type', 'admin')->whereNotNull('email')->get();
            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(new AdminNewUserNotificationMail($user));
            }
        } catch (\Exception $e) {
            Log::error('AdminNewUserNotificationMail failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
