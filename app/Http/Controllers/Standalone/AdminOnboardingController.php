<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class AdminOnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {
        $this->middleware(['auth', 'verified', 'role:admin']);
    }

    /**
     * Welcome phase
     */
    public function welcome()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $phases = $this->onboardingService->getPhasesForType('admin');

        return view('standalone.admin.onboarding.welcome', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completeWelcome(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $this->onboardingService->completePhase($progress, 'welcome');

        return redirect()->route('admin.onboarding.campaigns');
    }

    /**
     * Campaign approval tutorial phase
     */
    public function campaignApproval()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $phases = $this->onboardingService->getPhasesForType('admin');

        return view('standalone.admin.onboarding.campaigns', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completeCampaignApproval(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $this->onboardingService->completePhase($progress, 'campaign_approval');

        return redirect()->route('admin.onboarding.fraud');
    }

    /**
     * Fraud management tutorial phase
     */
    public function fraudManagement()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $phases = $this->onboardingService->getPhasesForType('admin');

        return view('standalone.admin.onboarding.fraud', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completeFraudManagement(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $this->onboardingService->completePhase($progress, 'fraud_management');

        return redirect()->route('admin.onboarding.payouts');
    }

    /**
     * Payout processing tutorial phase
     */
    public function payoutProcessing()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $phases = $this->onboardingService->getPhasesForType('admin');

        return view('standalone.admin.onboarding.payouts', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completePayoutProcessing(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $this->onboardingService->completePhase($progress, 'payout_processing');

        // Onboarding complete! Redirect to dashboard
        return redirect()->route('admin.dashboard')->with('success', 'Admin onboarding complete! You now have full access to all admin features.');
    }

    /**
     * Skip onboarding
     */
    public function skip(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'admin');
        $this->onboardingService->skipOnboarding($progress);

        return redirect()->route('admin.dashboard')->with('info', 'Onboarding skipped. You can access tutorial resources anytime.');
    }
}
