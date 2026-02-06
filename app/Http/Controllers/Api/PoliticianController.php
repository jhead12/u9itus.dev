<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Models\PoliticalCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * REST API for politicians to manage their profiles and campaigns.
 * These endpoints are consumed by the Wix dashboard components.
 */
class PoliticianController extends Controller
{
    /**
     * Register / create a politician profile.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'full_name'         => 'required|string|max:255',
            'political_office'  => 'nullable|string|in:' . implode(',', config('dial4dough.political_offices', [])),
            'governance_level'  => 'nullable|string|in:' . implode(',', array_keys(config('dial4dough.governance_levels', []))),
            'district'          => 'nullable|string|max:255',
            'party_affiliation' => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:2',
            'city'              => 'nullable|string|max:255',
            'website_url'       => 'nullable|url|max:500',
            'bio'               => 'nullable|string|max:2000',
            'wix_member_id'     => 'nullable|string',
            'wix_site_id'       => 'nullable|integer',
        ])->validate();

        $politician = Politician::create($validated);

        return response()->json([
            'message'    => 'Politician profile created',
            'politician' => $politician,
        ], 201);
    }

    /**
     * Get politician profile.
     */
    public function show(Politician $politician): JsonResponse
    {
        $politician->load('campaigns');

        return response()->json([
            'politician' => $politician,
            'stats' => [
                'total_campaigns'     => $politician->campaigns()->count(),
                'active_campaigns'    => $politician->campaigns()->where('status', 'active')->count(),
                'total_views'         => $politician->total_views_received,
                'total_spent'         => $politician->total_spent,
            ],
        ]);
    }

    /**
     * Update politician profile.
     */
    public function update(Request $request, Politician $politician): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'full_name'         => 'sometimes|string|max:255',
            'political_office'  => 'nullable|string',
            'governance_level'  => 'nullable|string',
            'district'          => 'nullable|string|max:255',
            'party_affiliation' => 'nullable|string|max:100',
            'state'             => 'nullable|string|max:2',
            'city'              => 'nullable|string|max:255',
            'bio'               => 'nullable|string|max:2000',
        ])->validate();

        $politician->update($validated);

        return response()->json([
            'message'    => 'Profile updated',
            'politician' => $politician->fresh(),
        ]);
    }

    /**
     * Create a new political campaign (video message or live feed).
     */
    public function createCampaign(Request $request, Politician $politician): JsonResponse
    {
        $validated = Validator::make($request->all(), [
            'title'                 => 'required|string|max:255',
            'message_summary'       => 'nullable|string|max:2000',
            'campaign_type'         => 'required|in:video,live_feed',
            'governance_level'      => 'nullable|string',
            'media_url'             => 'required_if:campaign_type,video|nullable|url',
            'media_duration'        => 'required_if:campaign_type,video|nullable|integer|min:' . config('dial4dough.min_video_duration') . '|max:' . config('dial4dough.max_video_duration'),
            'live_feed_url'         => 'required_if:campaign_type,live_feed|nullable|url',
            'live_scheduled_at'     => 'required_if:campaign_type,live_feed|nullable|date|after:now',
            'total_budget'          => 'required|numeric|min:6',   // minimum 10 views × $0.60
            'total_views_requested' => 'required|integer|min:10',
            'target_states'         => 'nullable|array',
            'target_cities'         => 'nullable|array',
            'target_districts'      => 'nullable|array',
        ])->validate();

        $validated['politician_id']             = $politician->id;
        $validated['revenue_per_view']          = config('dial4dough.revenue_per_view', 0.60);
        $validated['voter_payout_per_view']     = config('dial4dough.viewer_payout_per_view', 0.25);
        $validated['head_enterprises_fee_percent'] = config('dial4dough.head_enterprises_fee_percent', 15);
        $validated['status']                    = 'draft';
        $validated['approval_status']           = 'pending';

        $campaign = PoliticalCampaign::create($validated);

        return response()->json([
            'message'  => 'Campaign created and pending approval',
            'campaign' => $campaign,
        ], 201);
    }

    /**
     * List campaigns for a politician.
     */
    public function campaigns(Politician $politician): JsonResponse
    {
        $campaigns = $politician->campaigns()
            ->withCount('viewSessions')
            ->latest()
            ->paginate(20);

        return response()->json($campaigns);
    }

    /**
     * Get campaign details including view analytics.
     */
    public function campaignShow(Politician $politician, PoliticalCampaign $campaign): JsonResponse
    {
        $campaign->loadCount(['viewSessions', 'viewSessions as completed_views_count' => function ($q) {
            $q->where('status', 'completed');
        }]);

        return response()->json([
            'campaign' => $campaign,
            'analytics' => [
                'views_completed'   => $campaign->views_completed,
                'views_remaining'   => $campaign->remainingViews(),
                'budget_remaining'  => $campaign->remainingBudget(),
                'completion_rate'   => $campaign->total_views_requested > 0
                    ? round(($campaign->views_completed / $campaign->total_views_requested) * 100, 1)
                    : 0,
            ],
        ]);
    }
}
