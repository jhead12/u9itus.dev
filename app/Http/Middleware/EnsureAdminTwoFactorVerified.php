<?php

namespace App\Http\Middleware;

use App\Services\PlatformSettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminTwoFactorVerified
{
    /**
     * Enforce admin TOTP verification only when the global policy is enabled.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            return $next($request);
        }

        $isEnforced = filter_var(
            PlatformSettingsService::get('admin_2fa_enforced', null, false),
            FILTER_VALIDATE_BOOLEAN
        );

        if (!$isEnforced) {
            return $next($request);
        }

        if ($request->routeIs('admin.2fa.*')) {
            return $next($request);
        }

        if (!$user->hasAdminTwoFactorEnabled()) {
            return redirect()
                ->route('admin.2fa.setup')
                ->with('warning', 'Admin two-factor authentication is required before continuing.');
        }

        $verifiedUserId = (int) $request->session()->get('admin_2fa_verified_user_id', 0);
        $verifiedAt = $request->session()->get('admin_2fa_verified_at');

        if ($verifiedUserId !== (int) $user->id || !$verifiedAt) {
            return redirect()->route('admin.2fa.challenge');
        }

        $expiresInMinutes = (int) config('platform.standalone.auth.admin_2fa.session_ttl_minutes', 120);
        $verifiedTimestamp = strtotime((string) $verifiedAt);

        if (!$verifiedTimestamp || ($verifiedTimestamp + ($expiresInMinutes * 60)) < time()) {
            $request->session()->forget(['admin_2fa_verified_user_id', 'admin_2fa_verified_at']);

            return redirect()
                ->route('admin.2fa.challenge')
                ->withErrors(['code' => 'Your two-factor session expired. Please verify again.']);
        }

        return $next($request);
    }
}
