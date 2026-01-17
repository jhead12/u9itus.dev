<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\User;
use App\Services\AdminAssignmentService;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    protected $assignmentService;

    public function __construct(AdminAssignmentService $assignmentService)
    {
        $this->assignmentService = $assignmentService;
    }

    public function index()
    {
        $availableViewers = $this->assignmentService->getAvailableViewers();
        $campaignsNeedingViewers = $this->assignmentService->getCampaignsNeedingViewers();

        return view('admin.assignments.index', compact('availableViewers', 'campaignsNeedingViewers'));
    }

    public function assignAd(Request $request)
    {
        $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'viewer_id' => 'required|exists:users,id',
        ]);

        try {
            $campaign = Campaign::findOrFail($request->campaign_id);
            $viewer = User::findOrFail($request->viewer_id);
            
            $assignment = $this->assignmentService->assignAdToViewer($campaign, $viewer, auth()->user());

            return redirect()->back()->with('success', 'Ad successfully assigned to viewer.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to assign ad: ' . $e->getMessage());
        }
    }

    public function autoAssign(Request $request)
    {
        $limit = $request->input('limit', 10);

        try {
            $assignments = $this->assignmentService->autoAssignAds($limit);

            $message = count($assignments) > 0 
                ? 'Successfully auto-assigned ' . count($assignments) . ' ads to viewers.'
                : 'No assignments made. Check if viewers and campaigns are available.';

            return redirect()->back()->with('success', $message);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Auto-assignment failed: ' . $e->getMessage());
        }
    }
}
