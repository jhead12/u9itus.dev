<?php

namespace App\Http\Controllers\Wix;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\Voter;
use App\Models\PoliticalCampaign;
use App\Models\ViewSession;
use Illuminate\Http\Request;

/**
 * Dashboard page rendered inside the Wix Dashboard iframe.
 * Provides an overview for the site owner / admin.
 */
class DashboardController extends Controller
{
    /**
     * Main dashboard — overview stats.
     */
    public function index(Request $request)
    {
        $stats = [
            'total_politicians'   => Politician::count(),
            'total_voters'        => Voter::count(),
            'active_campaigns'    => PoliticalCampaign::where('status', 'active')->count(),
            'total_views'         => ViewSession::where('status', 'completed')->count(),
            'total_revenue'       => ViewSession::where('status', 'completed')->sum('platform_revenue'),
            'total_voter_payouts' => ViewSession::where('payment_status', 'paid')->sum('voter_payout_amount'),
            'pending_payouts'     => Voter::sum('pending_earnings'),
        ];

        return view('wix.dashboard.index', compact('stats'));
    }

    /**
     * Admin panel — manage politicians, voters, campaigns.
     */
    public function admin(Request $request)
    {
        $politicians      = Politician::with('campaigns')->latest()->paginate(20);
        $pendingCampaigns = PoliticalCampaign::where('approval_status', 'pending')->with('politician')->get();
        $flaggedVoters    = Voter::where('flagged_for_fraud', true)->get();

        return view('wix.dashboard.admin', compact('politicians', 'pendingCampaigns', 'flaggedVoters'));
    }
}
