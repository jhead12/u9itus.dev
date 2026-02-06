<?php

namespace App\Http\Controllers\Wix;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\Voter;
use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Models\PoliticalCampaign;
use App\Models\ViewSession;
use Illuminate\Contracts\View\View;
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
    public function index(Request $request): View
    {
        $stats = [
            'total_politicians'   => Politician::count(),
            'total_voters'        => Voter::count(),
            'active_campaigns'    => PoliticalCampaign::where('status', CampaignStatus::Active)->count(),
            'total_views'         => ViewSession::where('status', ViewSessionStatus::Completed)->count(),
            'total_revenue'       => ViewSession::where('status', ViewSessionStatus::Completed)->sum('platform_revenue'),
            'total_voter_payouts' => ViewSession::where('payment_status', ViewPaymentStatus::Paid)->sum('voter_payout_amount'),
            'pending_payouts'     => Voter::sum('pending_earnings'),
        ];

        return view('wix.dashboard.index', compact('stats'));
    }

    /**
     * Admin panel — manage politicians, voters, campaigns.
     */
    public function admin(Request $request): View
    {
        $politicians      = Politician::with('campaigns')->latest()->paginate(20);
        $pendingCampaigns = PoliticalCampaign::where('approval_status', ApprovalStatus::Pending)->with('politician')->get();
        $flaggedVoters    = Voter::where('flagged_for_fraud', true)->get();

        return view('wix.dashboard.admin', compact('politicians', 'pendingCampaigns', 'flaggedVoters'));
    }
}
