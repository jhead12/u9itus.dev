<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\ViewSessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CreateCampaignRequest;
use App\Http\Requests\StorePoliticianRequest;
use App\Http\Resources\CampaignResource;
use App\Http\Resources\PoliticianResource;
use App\Models\Politician;
use App\Models\PoliticalCampaign;
use App\Services\PlatformSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * REST API for politicians to manage their profiles and campaigns.
 *
 * These endpoints are consumed by the dashboard components
 * and are protected by the auth:sanctum middleware (see routes/api.php).
 */
class PoliticianController extends Controller
{
    private const CAMPAIGN_NOT_FOUND_MESSAGE = 'Campaign not found for this politician';

    /**
     * Register / create a politician profile.
     */
    public function store(StorePoliticianRequest $request): JsonResponse
    {
        $politician = Politician::create($request->validated());

        return response()->json([
            'message'    => 'Politician profile created',
            'politician' => new PoliticianResource($politician),
        ], 201);
    }

    /**
     * Get politician profile with stats.
     */
    public function show(Politician $politician): JsonResponse
    {
        $politician->load('campaigns');

        return response()->json([
            'politician' => new PoliticianResource($politician),
            'stats'      => [
                'total_campaigns'  => $politician->campaigns()->count(),
                'active_campaigns' => $politician->campaigns()->where('status', CampaignStatus::Active)->count(),
                'total_views'      => $politician->total_views_received,
                'total_spent'      => $politician->total_spent,
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
            'politician' => new PoliticianResource($politician->fresh()),
        ]);
    }

    /**
     * Create a new political campaign (video message or live feed).
     */
    public function createCampaign(CreateCampaignRequest $request, Politician $politician): JsonResponse
    {
        $validated = $request->validated();

        $validated['politician_id']                = $politician->id;
        $validated['revenue_per_view']             = (float) PlatformSettingsService::get('revenue_per_view', null, (float) config('u9itus.revenue_per_view', 1.00));
        $validated['voter_payout_per_view']        = (float) PlatformSettingsService::get('viewer_payout_per_view', null, (float) config('u9itus.viewer_payout_per_view', 0.50));
        $validated['head_enterprises_fee_percent'] = (float) PlatformSettingsService::get('head_enterprises_fee_percent', null, 15);
        $validated['status']                       = CampaignStatus::Draft;
        $validated['approval_status']              = ApprovalStatus::Pending;

        $campaign = PoliticalCampaign::create($validated);

        return response()->json([
            'message'  => 'Campaign created and pending approval',
            'campaign' => new CampaignResource($campaign),
        ], 201);
    }

    /**
     * List campaigns for a politician (paginated).
     */
    public function campaigns(Politician $politician): JsonResponse
    {
        $campaigns = $politician->campaigns()
            ->withCount('viewSessions')
            ->latest()
            ->paginate(20);

        return response()->json(CampaignResource::collection($campaigns)->resource);
    }

    /**
     * Get campaign details including view analytics.
     */
    public function campaignShow(Politician $politician, PoliticalCampaign $campaign): JsonResponse
    {
        if ((int) $campaign->politician_id !== (int) $politician->id) {
            return response()->json(['message' => self::CAMPAIGN_NOT_FOUND_MESSAGE], 404);
        }

        $campaign->loadCount(['viewSessions', 'viewSessions as completed_views_count' => function ($q) {
            $q->where('status', ViewSessionStatus::Completed);
        }]);

        return response()->json([
            'campaign'  => new CampaignResource($campaign),
            'analytics' => [
                'views_completed'  => $campaign->views_completed,
                'views_remaining'  => $campaign->remainingViews(),
                'budget_remaining' => $campaign->remainingBudget(),
                'completion_rate'  => $campaign->total_views_requested > 0
                    ? round(($campaign->views_completed / $campaign->total_views_requested) * 100, 1)
                    : 0,
            ],
        ]);
    }

    /**
     * Pause a politician campaign.
     */
    public function pauseCampaign(Politician $politician, PoliticalCampaign $campaign): JsonResponse
    {
        $status = 200;

        if ((int) $campaign->politician_id !== (int) $politician->id) {
            $status  = 404;
            $payload = ['message' => self::CAMPAIGN_NOT_FOUND_MESSAGE];
        } elseif ((int) $politician->user_id !== (int) Auth::id()) {
            $status  = 403;
            $payload = ['message' => 'Forbidden'];
        } elseif ($campaign->status !== CampaignStatus::Active) {
            $status  = 422;
            $payload = ['message' => 'Only active campaigns can be paused'];
        } else {
            $campaign->update(['status' => CampaignStatus::Paused]);

            $payload = [
                'message'  => 'Campaign paused',
                'campaign' => new CampaignResource($campaign->fresh()),
            ];
        }

        return response()->json($payload, $status);
    }

    /**
     * Resume a paused politician campaign.
     */
    public function resumeCampaign(Politician $politician, PoliticalCampaign $campaign): JsonResponse
    {
        $status = 200;

        if ((int) $campaign->politician_id !== (int) $politician->id) {
            $status  = 404;
            $payload = ['message' => self::CAMPAIGN_NOT_FOUND_MESSAGE];
        } elseif ((int) $politician->user_id !== (int) Auth::id()) {
            $status  = 403;
            $payload = ['message' => 'Forbidden'];
        } elseif ($campaign->status !== CampaignStatus::Paused) {
            $status  = 422;
            $payload = ['message' => 'Only paused campaigns can be resumed'];
        } else {
            $campaign->update(['status' => CampaignStatus::Active]);

            $payload = [
                'message'  => 'Campaign resumed',
                'campaign' => new CampaignResource($campaign->fresh()),
            ];
        }

        return response()->json($payload, $status);
    }
}
