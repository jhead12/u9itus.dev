<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce TOTP 2FA verification for all non-admin users.
 *
 * - When the user has 2FA enabled: always require a fresh session challenge.
 * - When a per-tier enforcement setting is active (voter_2fa_enforced /
 *   politician_2fa_enforced / citizen_2fa_enforced): redirect to setup if
 *   they haven't enrolled yet.
 * - Admins are handled by EnsureAdminTwoFactorVerified and are skipped here.
 */
class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $user->hasRole('admin')) {
            return $next($request);
        }

        // Guest-trial voters (see ProvisionGuestVoterSession) must never be
        // routed into 2FA setup, regardless of per-tier enforcement settings.
        if ($user->is_guest) {
            return $next($request);
        }

        // Allow the 2FA routes themselves to pass through (avoid redirect loop).
        if ($request->routeIs('2fa.*')) {
            return $next($request);
        }

        $role = $user->user_type; // voter | politician | citizen

        // Check per-role enforcement policy.
        $enforcedKey = "{$role}_2fa_enforced";
        $isEnforced  = filter_var(
            PlatformSettingsService::get($enforcedKey, null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        if ($isEnforced && !$user->hasTwoFactorEnabled()) {
            return redirect()
                ->route('2fa.setup')
                ->with('warning', 'Two-factor authentication is required for your account. Please set it up to continue.');
        }

        // If the user has 2FA enabled, verify they have a live session marker.
        if ($user->hasTwoFactorEnabled()) {
            $verifiedUserId = (int) $request->session()->get('2fa_verified_user_id', 0);
            $verifiedAt     = $request->session()->get('2fa_verified_at');

            if ($verifiedUserId !== (int) $user->id || !$verifiedAt) {
                return redirect()->route('2fa.challenge');
            }

            $expiresInMinutes = (int) config('platform.standalone.auth.two_factor.session_ttl_minutes', 120);
            $verifiedTimestamp = strtotime((string) $verifiedAt);

            if (!$verifiedTimestamp || ($verifiedTimestamp + ($expiresInMinutes * 60)) < time()) {
                $request->session()->forget(['2fa_verified_user_id', '2fa_verified_at']);

                return redirect()
                    ->route('2fa.challenge')
                    ->withErrors(['code' => 'Your two-factor session expired. Please verify again.']);
            }
        }

        return $next($request);
    }
}
