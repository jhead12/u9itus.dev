<?php

namespace App\Http\Middleware;

use App\Models\OnboardingProgress;
use App\Models\User;
use App\Models\Voter;
use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Silently provisions an anonymous visitor into a real, flagged
 * (`is_guest=true`) voter session when admin-controlled "Guest Trial Mode"
 * (PlatformSettingsService key `guest_trial_mode_enabled`) is active — so
 * they can use favorites/notes/the voter dashboard with zero login screen.
 *
 * Registered ahead of `auth` on the shared "Protected Application Routes"
 * group in routes/standalone.php — that group's `auth` middleware would
 * otherwise redirect an anonymous visitor to /login before a `guest.trial`
 * middleware scoped only to the inner `voter` prefix group ever got a
 * chance to run. Since it therefore sees every request under that group
 * (politician/citizen/admin routes included), it only acts on `/voter/*`
 * paths and is a no-op for everything else.
 */
class ProvisionGuestVoterSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check() || !$request->is('voter/*')) {
            return $next($request);
        }

        $trialActive = filter_var(
            PlatformSettingsService::get('guest_trial_mode_enabled', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$trialActive) {
            return $next($request);
        }

        $rateLimitKey = 'guest-provision:'.$request->ip();

        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return $next($request);
        }

        RateLimiter::hit($rateLimitKey, 3600);

        $trialDays = (int) PlatformSettingsService::get('guest_trial_duration_days', null, 30);

        $user = User::create([
            'email' => 'guest-'.Str::uuid().'@guest.u9itus.local',
            // Sentinel address is never actually verified by the guest, but
            // marking it verified up front lets Laravel's `verified`
            // middleware (which wraps this whole route group) pass them
            // through without a real email/inbox to click through.
            'email_verified_at' => now(),
            'password' => null,
            'user_type' => 'voter',
            'platform' => 'standalone',
            'is_guest' => true,
            'guest_expires_at' => now()->addDays($trialDays),
        ]);

        $user->assignRole('voter');

        Voter::create([
            'user_id' => $user->id,
            'full_name' => 'Guest Voter',
            'email' => $user->email,
            'wallet_balance' => 0,
            'trust_score' => 100,
            'is_active' => true,
            'is_verified' => false,
        ]);

        OnboardingProgress::updateOrCreate(
            ['user_id' => $user->id],
            [
                'user_type' => 'voter',
                'current_phase' => 'welcome',
                'completed_phases' => [],
                'is_completed' => true,
                'skipped' => true,
                'completed_at' => now(),
            ]
        );

        Auth::login($user, remember: true);

        return $next($request);
    }
}
