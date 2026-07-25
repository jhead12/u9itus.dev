<?php

namespace App\Http\Middleware;

use App\Services\UserRoleService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckUserRole Middleware
 *
 * Ensures every authenticated user has a resolvable role before they reach any
 * protected page. The `user_type` column is the canonical source of truth;
 * missing Spatie roles are repaired automatically via UserRoleService.
 */
class CheckUserRole
{
    public function __construct(
        private readonly UserRoleService $roleService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        $role = $this->roleService->resolvePrimaryRole($user);

        if ($role !== null) {
            $this->roleService->repairSpatieRole($user);
            return $next($request);
        }

        // No role and no user_type — account is genuinely incomplete.
        Log::error('CheckUserRole: user has no role and no user_type', [
            'user_id' => $user->id,
            'email'   => $user->email,
            'url'     => $request->url(),
        ]);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('register')
            ->with('error', 'Your account type could not be determined. Please register again or contact support.');
    }
}
