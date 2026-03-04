<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Politician;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class PoliticianOnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}


    /**
     * Welcome phase
     */
    public function welcome()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $phases = $this->onboardingService->getPhasesForType('politician');

        return view('standalone.politician.onboarding.welcome', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completeWelcome(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $this->onboardingService->completePhase($progress, 'welcome');

        return redirect()->route('politician.onboarding.profile');
    }

    /**
     * Political profile setup phase
     */
    public function politicalProfile()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $phases = $this->onboardingService->getPhasesForType('politician');
        $politician = Politician::where('user_id', auth()->id())->first();

        return view('standalone.politician.onboarding.profile', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
            'politician' => $politician,
        ]);
    }

    public function completePoliticalProfile(Request $request)
    {
        $validated = $request->validate([
            'governance_level' => 'required|string|max:50',
            'office_title' => 'required|string|max:100',
            'party_affiliation' => 'required|string|max:50',
            'bio' => 'nullable|string|max:1000',
            'state' => 'nullable|string|max:2',
            'district' => 'nullable|string|max:50',
        ]);

        // Update politician profile
        $politician = Politician::where('user_id', auth()->id())->first();
        if ($politician) {
            // Map office_title to political_office for database storage
            $updateData = [
                'governance_level' => $validated['governance_level'],
                'political_office' => $validated['office_title'], // Map to correct field
                'party_affiliation' => $validated['party_affiliation'],
                'bio' => $validated['bio'] ?? null,
                'state' => $validated['state'] ?? null,
                'district' => $validated['district'] ?? null,
            ];
            $politician->update($updateData);
        }

        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $this->onboardingService->completePhase($progress, 'political_profile', $validated);

        return redirect()->route('politician.onboarding.payment');
    }

    /**
     * Payment method setup phase
     */
    public function paymentMethod()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $phases = $this->onboardingService->getPhasesForType('politician');

        return view('standalone.politician.onboarding.payment', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completePaymentMethod(Request $request)
    {
        // Payment method setup would be handled by Stripe integration
        // For now, we'll just mark this phase as complete
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $this->onboardingService->completePhase($progress, 'payment_method');

        return redirect()->route('politician.onboarding.campaign');
    }

    /**
     * First campaign creation phase
     */
    public function firstCampaign()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $phases = $this->onboardingService->getPhasesForType('politician');

        return view('standalone.politician.onboarding.campaign', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completeFirstCampaign(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $this->onboardingService->completePhase($progress, 'first_campaign');

        return redirect()->route('politician.onboarding.credits');
    }

    /**
     * Add credits phase
     */
    public function addCredits()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $phases = $this->onboardingService->getPhasesForType('politician');

        return view('standalone.politician.onboarding.credits', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completeAddCredits(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $this->onboardingService->completePhase($progress, 'add_credits');

        // Onboarding complete! Redirect to dashboard
        return redirect()->route('politician.dashboard')->with('success', 'Onboarding complete! You\'re ready to launch your first campaign.');
    }

    /**
     * Skip onboarding
     */
    public function skip(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'politician');
        $this->onboardingService->skipOnboarding($progress);

        return redirect()->route('politician.dashboard')->with('info', 'Onboarding skipped. You can access tutorial resources anytime.');
    }
}
