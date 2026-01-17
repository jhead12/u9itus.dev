<?php

namespace App\Http\Controllers\Viewer;

use App\Http\Controllers\Controller;
use App\Models\AdAssignment;
use App\Services\ViewTrackingService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    protected $viewTrackingService;

    public function __construct(ViewTrackingService $viewTrackingService)
    {
        $this->middleware(['auth', 'role:viewer']);
        $this->viewTrackingService = $viewTrackingService;
    }

    public function index()
    {
        $user = auth()->user();
        $viewer = $user->viewer;

        $currentAssignment = $user->currentAssignment;
        
        $recentAssignments = AdAssignment::where('viewer_id', $user->id)
            ->with('campaign')
            ->latest()
            ->take(10)
            ->get();

        $stats = [
            'total_earned' => $viewer->total_earned,
            'pending_earnings' => $viewer->pending_earnings,
            'total_views' => $viewer->total_views,
            'trust_score' => $viewer->trust_score,
        ];

        return view('viewer.dashboard', compact('currentAssignment', 'recentAssignments', 'stats'));
    }

    public function watch(AdAssignment $assignment)
    {
        $this->authorize('view', $assignment);

        if ($assignment->status === 'assigned') {
            $this->viewTrackingService->trackViewStart($assignment);
        }

        return view('viewer.watch', compact('assignment'));
    }

    public function complete(AdAssignment $assignment, Request $request)
    {
        $this->authorize('complete', $assignment);

        $request->validate([
            'watch_time' => 'required|integer|min:1',
        ]);

        try {
            $this->viewTrackingService->completeView($assignment, $request->watch_time);

            $meetsRequirement = $this->viewTrackingService->validateViewCompletion($assignment);
            
            $message = $meetsRequirement 
                ? 'Ad watched successfully! Payment approved.' 
                : 'Ad completed, but watch time requirement not met. No payment earned.';

            return redirect()->route('viewer.dashboard')->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to complete view: ' . $e->getMessage());
        }
    }
}
