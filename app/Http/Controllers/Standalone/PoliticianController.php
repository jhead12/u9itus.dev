<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Standalone Politician Controller
 * 
 * Handles politician-specific features in standalone mode:
 * - Campaign management
 * - Video uploads
 * - Analytics
 * - Billing
 */
class PoliticianController extends Controller
{
    /**
     * Show the politician dashboard.
     */
    public function dashboard()
    {
        $user = Auth::user();
        $politician = $user->politician;

        if (! $politician) {
            return view('standalone.dashboard.index', ['user' => $user]);
        }

        // Load recent campaigns (eager load to avoid N+1)
        $recentCampaigns = $politician->campaigns()
            ->orderByDesc('created_at')
            ->take(5)
            ->get();

        // Compute credit balance from latest ledger entry
        $creditBalance = PoliticianCredit::where('politician_id', $politician->id)
            ->orderByDesc('created_at')
            ->value('balance_after') ?? 0.00;

        $stats = [
            'active_campaigns'  => $politician->campaigns()->where('status', 'active')->count(),
            'total_campaigns'   => $politician->campaigns()->count(),
            'total_views'       => $politician->total_views_received ?? 0,
            'total_spent'       => $politician->total_spent ?? 0.00,
            'credit_balance'    => $creditBalance,
        ];

        return view('standalone.politician.dashboard', [
            'user'            => $user,
            'politician'      => $politician,
            'recentCampaigns' => $recentCampaigns,
            'stats'           => $stats,
        ]);
    }

    // ─── Campaign CRUD ────────────────────────────────────────────────────────

    /** List all campaigns for this politician. */
    public function campaigns()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $campaigns = $politician->campaigns()
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('standalone.politician.campaigns.index', compact('campaigns', 'politician'));
    }

    /** Show campaign creation form. */
    public function createCampaign()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $revenuePerView = config('u9itus.revenue_per_view', 0.60);
        $states = config('u9itus.us_states', []);
        $governanceLevels = config('u9itus.governance_levels', [
            'Federal' => 'Federal', 'State' => 'State', 'County' => 'County',
            'City' => 'City', 'School Board' => 'School Board',
        ]);

        return view('standalone.politician.campaigns.create', compact(
            'politician', 'revenuePerView', 'states', 'governanceLevels'
        ));
    }

    /** Store a new campaign. */
    public function storeCampaign(CreateCampaignRequest $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $data = $request->validated();
        $data['politician_id'] = $politician->id;
        $data['status']        = 'draft';
        $data['revenue_per_view'] = config('u9itus.revenue_per_view', 0.60);
        $data['voter_payout_per_view'] = config('u9itus.viewer_payout_per_view', 0.25);

        $campaign = PoliticalCampaign::create($data);

        return redirect()
            ->route('politician.campaigns.show', $campaign)
            ->with('success', 'Campaign created! Upload a video and submit for review when ready.');
    }

    /** Show campaign detail. */
    public function showCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $campaign->load('viewSessions');
        $completedViews = $campaign->views_completed ?? 0;
        $budgetUsed     = $campaign->amount_spent ?? 0;
        $budgetLeft     = ($campaign->total_budget ?? 0) - $budgetUsed;

        return view('standalone.politician.campaigns.show', compact(
            'campaign', 'politician', 'completedViews', 'budgetUsed', 'budgetLeft'
        ));
    }

    /** Show campaign edit form. */
    public function editCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused']),
            403
        );

        $states = config('u9itus.us_states', []);
        $governanceLevels = config('u9itus.governance_levels', [
            'Federal' => 'Federal', 'State' => 'State', 'County' => 'County',
            'City' => 'City', 'School Board' => 'School Board',
        ]);

        return view('standalone.politician.campaigns.edit', compact(
            'campaign', 'politician', 'states', 'governanceLevels'
        ));
    }

    /** Update a campaign. */
    public function updateCampaign(UpdateCampaignRequest $request, PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused']),
            403
        );

        $campaign->update($request->validated());

        return redirect()
            ->route('politician.campaigns.show', $campaign)
            ->with('success', 'Campaign updated successfully.');
    }

    /** Delete a campaign (draft/cancelled only). */
    public function destroyCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'cancelled']),
            403
        );

        $campaign->delete();

        return redirect()
            ->route('politician.campaigns.index')
            ->with('success', 'Campaign deleted.');
    }

    /** Pause an active campaign. */
    public function pauseCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $campaign->update(['status' => 'paused']);

        return back()->with('success', 'Campaign paused.');
    }

    /** Resume a paused campaign. */
    public function resumeCampaign(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $campaign->update(['status' => 'active']);

        return back()->with('success', 'Campaign resumed.');
    }

    // ─── Other pages ───────────────────────────────────────────────────────────

    /** Submit a draft campaign for admin approval. */
    public function submitForReview(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id,
            403
        );

        abort_unless(
            ($campaign->status?->value ?? $campaign->status) === 'draft',
            422,
            'Only draft campaigns can be submitted for review.'
        );

        abort_unless(
            $campaign->media_url || $campaign->live_feed_url,
            422,
            'Please upload a video or set a live stream URL before submitting.'
        );

        $campaign->update(['status' => 'pending_approval', 'approval_status' => 'pending']);

        return back()->with('success', 'Campaign submitted for review. You will be notified once approved.');
    }

    /** Handle video file upload for a campaign (local/S3). */
    public function uploadVideo(Request $request, PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless(
            $politician && (int) $campaign->politician_id === (int) $politician->id
            && in_array($campaign->status?->value ?? $campaign->status, ['draft', 'paused']),
            403
        );

        $request->validate([
            'video' => [
                'required', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm',
                'max:' . (config('u9itus.max_video_size_mb', 500) * 1024),
            ],
        ]);

        $file = $request->file('video');
        $disk = config('filesystems.default', 'local');

        // Delete old video if present
        if ($campaign->media_url) {
            $oldPath = parse_url($campaign->media_url, PHP_URL_PATH);
            Storage::disk($disk)->delete(ltrim($oldPath, '/'));
        }

        $path = $file->store("campaigns/{$campaign->id}/video", $disk);
        $url  = Storage::disk($disk)->url($path);

        $campaign->update(['media_url' => $url]);

        return back()->with('success', 'Video uploaded successfully.');
    }

    /** Overall analytics for this politician (all campaigns). */
    public function analytics()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $campaigns = $politician->campaigns()
            ->withCount(['viewSessions as total_sessions'])
            ->orderByDesc('created_at')
            ->get();

        $totalViews    = $campaigns->sum('views_completed');
        $totalSpent    = $campaigns->sum('amount_spent');
        $totalBudget   = $campaigns->sum('total_budget');
        $activeCampaigns = $campaigns->where('status.value', 'active')
            ->merge($campaigns->whereStrict('status', 'active'))
            ->unique('id')
            ->count();

        return view('standalone.politician.analytics', compact(
            'politician', 'campaigns',
            'totalViews', 'totalSpent', 'totalBudget', 'activeCampaigns'
        ));
    }

    /** Per-campaign analytics detail. */
    public function campaignAnalytics(PoliticalCampaign $campaign)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician && (int) $campaign->politician_id === (int) $politician->id, 403);

        $sessions = $campaign->viewSessions()
            ->orderByDesc('created_at')
            ->paginate(20);

        // Aggregate status breakdown
        $byStatus = $campaign->viewSessions()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->orderByDesc('total')
            ->get();

        $completedViews = $campaign->views_completed ?? 0;
        $budgetUsed     = $campaign->amount_spent ?? 0;
        $budgetLeft     = ($campaign->total_budget ?? 0) - $budgetUsed;

        return view('standalone.politician.analytics.campaign', compact(
            'campaign', 'politician', 'sessions', 'byStatus',
            'completedViews', 'budgetUsed', 'budgetLeft'
        ));
    }

    /** Billing overview: credit balance + transaction history. */
    public function billing()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $creditBalance = PoliticianCredit::where('politician_id', $politician->id)
            ->orderByDesc('created_at')->value('balance_after') ?? 0.00;

        $credits = PoliticianCredit::where('politician_id', $politician->id)
            ->orderByDesc('created_at')->paginate(15);

        $transactions = CampaignTransaction::where('politician_id', $politician->id)
            ->orderByDesc('created_at')->paginate(15);

        return view('standalone.politician.billing', compact(
            'politician', 'creditBalance', 'credits', 'transactions'
        ));
    }

    /** Add funds via Stripe — creates a PaymentIntent and redirects to checkout. */
    public function addFunds(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:10', 'max:10000'],
        ]);

        /** @var \App\Services\CampaignBillingService $billing */
        $billing = app(\App\Services\CampaignBillingService::class);

        $intentData = $billing->createPurchaseIntent($politician, (float) $validated['amount'], [
            'description' => 'Credit top-up for politician #' . $politician->id,
        ]);

        return redirect()->away($intentData['checkout_url'] ?? route('politician.billing'))
            ->with('info', 'Redirecting to payment…');
    }

    /** Invoice / transaction history page. */
    public function invoices()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $transactions = CampaignTransaction::where('politician_id', $politician->id)
            ->orderByDesc('created_at')
            ->paginate(25);

        return view('standalone.politician.invoices', compact('politician', 'transactions'));
    }

    /** Show profile edit form. */
    public function profile()
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $governanceLevels = config('u9itus.governance_levels', []);
        $states = config('u9itus.us_states', []);

        return view('standalone.politician.profile', compact('politician', 'governanceLevels', 'states'));
    }

    /** Save profile changes. */
    public function updateProfile(Request $request)
    {
        $politician = Auth::user()->politician;
        abort_unless($politician, 403);

        $validated = $request->validate([
            'full_name'         => 'required|string|max:255',
            'political_office'  => 'nullable|string|max:255',
            'governance_level'  => 'nullable|string|max:100',
            'district'          => 'nullable|string|max:100',
            'party_affiliation' => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:2',
            'city'              => 'nullable|string|max:100',
            'website_url'       => 'nullable|url|max:255',
            'bio'               => 'nullable|string|max:2000',
        ]);

        $politician->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }
}
