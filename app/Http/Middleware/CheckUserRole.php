<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckUserRole Middleware
 *
 * Ensures every authenticated user has a valid Spatie role before they reach
 * any protected page. If the role is missing but the user_type column is
 * populated, the role is silently repaired so subsequent requests are fast.
 * If neither exists the user is redirected to a dedicated "fix your account"
 * route with a clear error message.
 *
 * Applied to: all auth-protected routes via the 'check.role' alias.
 */
class CheckUserRole
{
    /** Role → named dashboard route. */
    private const ROLE_ROUTES = [
        'admin'      => 'admin.dashboard',
        'politician' => 'politician.dashboard',
        'voter'      => 'voter.dashboard',
        'citizen'    => 'citizen.dashboard',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // ── 1. Spatie role already assigned ──────────────────────────────────
        foreach (array_keys(self::ROLE_ROUTES) as $role) {
            if ($user->hasRole($role)) {
                return $next($request);
            }
        }

        // ── 2. Spatie role missing — try to repair from user_type column ─────
        $userType = $user->user_type ?? '';

        if (array_key_exists($userType, self::ROLE_ROUTES)) {
            Log::warning('CheckUserRole: repaired missing Spatie role', [
                'user_id'   => $user->id,
                'email'     => $user->email,
                'user_type' => $userType,
                'url'       => $request->url(),
            ]);

            try {
                // Railway/prod occasionally has users before role seed data exists.
                Role::findOrCreate($userType, config('auth.defaults.guard', 'web'));
                $user->assignRole($userType);
            } catch (\Throwable $e) {
                Log::error('CheckUserRole: failed to repair missing role', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'user_type' => $userType,
                    'url' => $request->url(),
                    'error' => $e->getMessage(),
                ]);

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')
                    ->with('error', 'Your account role could not be repaired. Please sign in again or contact support.');
            }

            // Redirect to the correct role dashboard now that the role is fixed.
            return redirect()->route(self::ROLE_ROUTES[$userType])
                ->with('status', 'Your account was updated. Welcome back!');
        }

        // ── 3. No role, no user_type — account is genuinely incomplete ────────
        Log::error('CheckUserRole: user has no role and no user_type', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'url'     => $request->url(),
        ]);

        // Log them out and redirect to registration with an explanation.
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('register')
            ->with('error', 'Your account type could not be determined. Please register again or contact support.');
    }
}
