<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Standalone Dashboard Controller
 * 
 * Main dashboard controller that routes users to appropriate
 * role-based dashboards.
 */
class DashboardController extends Controller
{
    /**
     * Show the main dashboard (redirects based on role).
     */
    public function index()
    {
        $user = Auth::user();

        // Check Spatie roles first (authoritative source)
        if ($user->hasRole('admin'))      return redirect()->route('admin.dashboard');
        if ($user->hasRole('politician')) return redirect()->route('politician.dashboard');
        if ($user->hasRole('voter'))      return redirect()->route('voter.dashboard');

        // Fallback: user_type column (catches users whose role row is missing)
        $destination = match($user->user_type ?? '') {
            'admin'      => route('admin.dashboard'),
            'politician' => route('politician.dashboard'),
            'voter'      => route('voter.dashboard'),
            default      => null,
        };

        if ($destination) {
            // Repair: assign the missing Spatie role so next login goes directly
            $user->assignRole($user->user_type);
            return redirect($destination);
        }

        // No role and no user_type — show the neutral placeholder
        return view('standalone.dashboard.index', ['user' => $user]);
    }

    /**
     * Handle contact form submission.
     */
    public function submitContact(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:1000',
        ]);

        // TODO: Send email to admin
        // TODO: Store in database

        return back()->with('success', 'Thank you for contacting us! We will respond shortly.');
    }
}
