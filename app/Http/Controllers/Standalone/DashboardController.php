<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Standalone Dashboard Controller
 *
 * Routes authenticated users to their role-specific dashboard.
 * By the time a request reaches this controller the CheckUserRole middleware
 * has already guaranteed the user has a valid Spatie role (or has been
 * redirected away), so the fallback branch here should never fire in practice.
 */
class DashboardController extends Controller
{
    /** Role → named dashboard route. */
    private const ROLE_ROUTES = [
        'admin'      => 'admin.dashboard',
        'politician' => 'politician.dashboard',
        'voter'      => 'voter.dashboard',
        'citizen'    => 'citizen.dashboard',
    ];

    /**
     * Show the main dashboard (redirects based on role).
     */
    public function index()
    {
        $user = Auth::user();

        // ── Dual-role voter + citizen ─────────────────────────────────────────
        // Users who upgraded from voter to citizen hold both roles. Send them
        // to the portal picker instead of arbitrarily picking one dashboard.
        if ($user->hasRole('voter') && $user->hasRole('citizen')) {
            return redirect()->route('portal-pick');
        }

        // ── Spatie roles (authoritative) ──────────────────────────────────────
        foreach (self::ROLE_ROUTES as $role => $routeName) {
            if ($user->hasRole($role)) {
                return redirect()->route($routeName);
            }
        }

        // ── Fallback: user_type column ────────────────────────────────────────
        // The CheckUserRole middleware should have already handled or repaired
        // this case. If we still reach here, attempt one more repair and log it.
        $userType = $user->user_type ?? '';

        if (array_key_exists($userType, self::ROLE_ROUTES)) {
            Log::warning('DashboardController: assigning missing Spatie role from user_type', [
                'user_id'   => $user->id,
                'email'     => $user->email,
                'user_type' => $userType,
            ]);

            $user->assignRole($userType);

            return redirect()->route(self::ROLE_ROUTES[$userType]);
        }

        // ── No role, no valid user_type ────────────────────────────────────────
        // CheckUserRole middleware already handles this path (logout + redirect).
        // This branch is a last-resort safety net.
        Log::error('DashboardController: user reached fallback with no resolvable role', [
            'user_id'   => $user->id,
            'email'     => $user->email,
            'user_type' => $userType,
            'roles'     => $user->getRoleNames(),
        ]);

        return view('standalone.dashboard.index', ['user' => $user]);
    }

    /**
     * Handle contact form submission.
     */
    public function submitContact(Request $request)
    {
        $request->validate([
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
        ]);

        // TODO: Send email to admin
        // TODO: Store in database

        return back()->with('success', 'Thank you for contacting us! We will respond shortly.');
    }
}
