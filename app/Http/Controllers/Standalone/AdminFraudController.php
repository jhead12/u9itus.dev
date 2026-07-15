<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Http\Request;

/**
 * Admin fraud-detection review surface.
 *
 * Split out of AdminController. Shows the fraud dashboard and flagged view
 * sessions, lets admins clear/void/confirm a flagged session, and clears the
 * fraud flag on a voter profile directly.
 */
class AdminFraudController extends Controller
{
    /**
     * Show fraud detection dashboard.
     */
    public function fraud()
    {
        $stats = [
            'flagged_sessions'   => ViewSession::where('fraud_score', '>', 50)->count(),
            'high_risk_sessions' => ViewSession::where('fraud_score', '>', 80)->count(),
            'total_sessions'     => ViewSession::count(),
        ];

        $flaggedSessions = ViewSession::with(['voter', 'campaign'])
            ->where('fraud_score', '>', 50)
            ->orderByDesc('fraud_score')
            ->take(10)
            ->get();

        return view('standalone.admin.fraud', compact('stats', 'flaggedSessions'));
    }

    /**
     * Show flagged views.
     */
    public function flaggedViews()
    {
        $sessions = ViewSession::with(['voter', 'campaign'])
            ->where('fraud_score', '>', 50)
            ->orderByDesc('fraud_score')
            ->paginate(30);

        return view('standalone.admin.fraud-views', compact('sessions'));
    }

    /**
     * Review a flagged view session.
     *
     * Actions:
     *   cleared   – false positive; reset fraud score, allow payout
     *   voided    – confirmed fraud; zero out payout, keep voter flagged
     *   confirmed – session looks suspicious but payout unchanged
     */
    public function reviewView(Request $request, $viewId)
    {
        $request->validate([
            'action' => ['required', 'in:cleared,voided,confirmed'],
        ]);

        $session = ViewSession::with('voter')->findOrFail($viewId);
        $action  = $request->input('action');

        $updates = [
            'reviewed_at'   => now(),
            'reviewed_by'   => auth()->id(),
            'review_action' => $action,
        ];

        if ($action === 'cleared') {
            $updates['fraud_score'] = 0;
            $updates['fraud_flags'] = [];
            if ($session->voter) {
                $session->voter->update([
                    'flagged_for_fraud' => false,
                    'trust_score'       => min(100, $session->voter->trust_score + 10),
                ]);
            }
        } elseif ($action === 'voided') {
            $updates['voter_payout_amount'] = 0;
            $updates['referral_commission']  = 0;
            $updates['payment_status']       = 'voided';
            if ($session->voter) {
                $session->voter->update(['flagged_for_fraud' => true]);
            }
        }

        $session->update($updates);

        return back()->with('success', "Session #{$viewId} marked as {$action}.");
    }

    /**
     * Clear fraud flag on a voter profile directly.
     */
    public function clearVoterFraud(Request $request, $voterId)
    {
        $voter = Voter::findOrFail($voterId);

        $voter->update([
            'flagged_for_fraud' => false,
            'trust_score'       => min(100, $voter->trust_score + 10),
        ]);

        return back()->with('success', 'Fraud flag cleared for voter.');
    }
}
