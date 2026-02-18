<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Standalone Voter Controller
 * 
 * Handles voter-specific features in standalone mode:
 * - Ad viewing (token-based)
 * - Earnings tracking
 * - Referrals
 * - Preferences
 */
class VoterController extends Controller
{
    /**
     * Show the voter dashboard.
     */
    public function dashboard()
    {
        $user = Auth::user();

        // Self-heal: create voter profile if registration created the user
        // but the voter record is missing (e.g. early registrations or retry failures).
        $voter = $user->voter ?? \App\Models\Voter::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name'      => $user->name,
                'email'          => $user->email,
                'phone'          => $user->phone,
                'wallet_balance' => 0,
                'trust_score'    => 100,
                'is_active'      => true,
                'is_verified'    => false,
            ]
        );

        $summary = [
            'wallet_balance'  => (float) ($voter->wallet_balance ?? 0),
            'pending_earnings'=> (float) ($voter->pending_earnings ?? 0),
            'total_earned'    => (float) ($voter->total_earned ?? 0),
            'total_views'     => (int)   ($voter->total_views ?? 0),
        ];

        $recentSessions = $voter->viewSessions()
            ->with('campaign')
            ->latest()
            ->take(10)
            ->get();

        return view('standalone.voter.dashboard', [
            'user'           => $user,
            'voter'          => $voter,
            'summary'        => $summary,
            'recentSessions' => $recentSessions,
        ]);
    }

    /**
     * Show ad watch page (secure token required).
     */
    public function watch(string $token)
    {
        // TODO: Validate token, load ad
        return view('standalone.voter.watch', [
            'token' => $token,
        ]);
    }

    /**
     * Mark ad viewing as started.
     */
    public function startWatching(string $token)
    {
        // TODO: Implement ad viewing start logic
        return response()->json(['status' => 'started']);
    }

    /**
     * Mark ad viewing as complete.
     */
    public function markComplete(string $token)
    {
        // TODO: Implement ad completion, credit earnings
        return response()->json(['status' => 'complete']);
    }

    /**
     * Show earnings page.
     */
    public function earnings()
    {
        return view('standalone.voter.earnings');
    }

    /**
     * Show earnings history.
     */
    public function earningsHistory()
    {
        return view('standalone.voter.earnings-history');
    }

    /**
     * Request payout.
     */
    public function requestPayout(Request $request)
    {
        // TODO: Implement payout request
        return back()->with('success', 'Payout requested successfully.');
    }

    /**
     * Show referrals page.
     */
    public function referrals()
    {
        return view('standalone.voter.referrals');
    }

    /**
     * Get referral link.
     */
    public function getReferralLink()
    {
        $user = Auth::user();
        // TODO: Generate referral link
        return response()->json(['link' => 'https://u9itus.com/register?ref=' . $user->referral_code]);
    }

    /**
     * Show preferences page.
     */
    public function preferences()
    {
        return view('standalone.voter.preferences');
    }

    /**
     * Update voter preferences.
     */
    public function updatePreferences(Request $request)
    {
        // TODO: Implement preferences update
        return back()->with('success', 'Preferences updated successfully.');
    }

    /**
     * Show voter profile.
     */
    public function profile()
    {
        return view('standalone.voter.profile');
    }

    /**
     * Update voter profile.
     */
    public function updateProfile(Request $request)
    {
        // TODO: Implement profile update
        return back()->with('success', 'Profile updated successfully.');
    }
}
