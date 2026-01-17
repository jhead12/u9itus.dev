<?php

namespace App\Http\Controllers\Advertiser;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CampaignController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'role:advertiser']);
    }

    public function index()
    {
        $user = auth()->user();
        $advertiser = $user->advertiser;
        
        if (!$advertiser) {
            abort(403, 'Advertiser profile not found.');
        }

        $campaigns = $advertiser->campaigns()
            ->latest()
            ->paginate(10);

        return view('advertiser.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        return view('advertiser.campaigns.create');
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        $advertiser = $user->advertiser;
        
        if (!$advertiser) {
            abort(403, 'Advertiser profile not found.');
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'campaign_type' => 'required|in:video,audio',
            'media_file' => 'required|file|mimes:mp4,mov,avi,mp3,wav|max:51200',
            'total_views_requested' => 'required|integer|min:1',
            'payment_per_view' => 'required|numeric|min:0.01',
            'min_watch_time_percent' => 'nullable|numeric|min:1|max:100',
            'target_states' => 'nullable|string',
            'target_cities' => 'nullable|string',
        ]);

        try {
            $mediaFile = $request->file('media_file');
            $mediaPath = $mediaFile->store('campaigns', 'public');
            
            // Calculate total budget including Head Enterprises fee
            $feePercent = config('dial4dough.head_enterprises_fee_percent', 15);
            $viewCost = $validated['payment_per_view'] * $validated['total_views_requested'];
            $totalBudget = $viewCost * (1 + ($feePercent / 100));
            
            // Parse target locations
            $targetStates = !empty($validated['target_states']) ? array_filter(array_map('trim', explode(',', $validated['target_states']))) : [];
            $targetCities = !empty($validated['target_cities']) ? array_filter(array_map('trim', explode(',', $validated['target_cities']))) : [];

            $campaign = $advertiser->campaigns()->create([
                'title' => $validated['title'],
                'description' => $validated['description'],
                'campaign_type' => $validated['campaign_type'],
                'media_file_url' => $mediaPath,
                'media_duration' => 15, // TODO: Calculate from actual media file
                'total_budget' => $totalBudget,
                'payment_per_view' => $validated['payment_per_view'],
                'head_enterprises_fee_percent' => $feePercent,
                'total_views_requested' => $validated['total_views_requested'],
                'views_completed' => 0,
                'target_states' => $targetStates,
                'target_cities' => $targetCities,
                'min_watch_time_percent' => $validated['min_watch_time_percent'] ?? config('dial4dough.min_watch_time_percent', 80),
                'status' => 'pending',
                'approval_status' => 'pending',
                'payment_status' => 'pending',
            ]);

            return redirect()->route('advertiser.campaigns.show', $campaign)
                ->with('success', 'Campaign created successfully. Pending admin approval.');
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to create campaign: ' . $e->getMessage());
        }
    }

    public function show(Campaign $campaign)
    {
        $this->authorize('view', $campaign);

        $campaign->load(['assignments' => function($query) {
            $query->where('status', 'completed')->with('viewer');
        }]);

        $analytics = [
            'completion_rate' => $campaign->total_views_requested > 0 
                ? ($campaign->views_completed / $campaign->total_views_requested) * 100 
                : 0,
            'total_spent' => $campaign->views_completed * $campaign->payment_per_view,
            'remaining_views' => $campaign->remainingViews(),
            'avg_watch_time' => $campaign->assignments()->where('status', 'completed')->avg('watch_time') ?? 0,
        ];

        return view('advertiser.campaigns.show', compact('campaign', 'analytics'));
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }
}
