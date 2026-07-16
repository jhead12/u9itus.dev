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
            return $this->deny(
                $request,
                'admin.2fa.setup',
                'Admin two-factor authentication is required before continuing.',
            );
        }

        $verifiedUserId = (int) $request->session()->get('admin_2fa_verified_user_id', 0);
        $verifiedAt = $request->session()->get('admin_2fa_verified_at');

        if ($verifiedUserId !== (int) $user->id || !$verifiedAt) {
            return $this->deny($request, 'admin.2fa.challenge', 'Two-factor verification required.');
        }

        $expiresInMinutes = (int) config('platform.standalone.auth.admin_2fa.session_ttl_minutes', 120);
        $verifiedTimestamp = strtotime((string) $verifiedAt);

        if (!$verifiedTimestamp || ($verifiedTimestamp + ($expiresInMinutes * 60)) < time()) {
            $request->session()->forget(['admin_2fa_verified_user_id', 'admin_2fa_verified_at']);

            return $this->deny(
                $request,
                'admin.2fa.challenge',
                'Your two-factor session expired. Please verify again.',
                ['code' => 'Your two-factor session expired. Please verify again.'],
            );
        }

        return $next($request);
    }

    /**
     * Deny an unverified admin. API/JSON callers get a 403 JSON response; web
     * (blade) callers get the redirect to the appropriate 2FA web route.
     *
     * @param  array<string, string>  $errors
     */
    private function deny(Request $request, string $route, string $message, array $errors = []): Response
    {
        if ($request->expectsJson()) {
            $payload = ['message' => $message];
            if ($errors) {
                $payload['errors'] = $errors;
            }

            return response()->json($payload, 403);
        }

        $redirect = redirect()->route($route)->with('warning', $message);

        if ($errors) {
            $redirect->withErrors($errors);
        }

        return $redirect;
    }
}
