<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Voter;
use App\Services\OnboardingService;
use Illuminate\Http\Request;

class VoterOnboardingController extends Controller
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}


    /**
     * Welcome phase
     */
    public function welcome()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $phases = $this->onboardingService->getPhasesForType('voter');

        return view('standalone.voter.onboarding.welcome', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completeWelcome(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $this->onboardingService->completePhase($progress, 'welcome');

        return redirect()->route('voter.onboarding.profile');
    }

    /**
     * Profile setup phase
     */
    public function profileSetup()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $phases = $this->onboardingService->getPhasesForType('voter');
        $voter = Voter::where('user_id', auth()->id())->first();

        return view('standalone.voter.onboarding.profile', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
            'voter' => $voter,
        ]);
    }

    public function completeProfileSetup(Request $request)
    {
        $validated = $request->validate([
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:2',
            'zip_code' => 'nullable|string|max:10',
        ]);

        // Update voter profile
        $voter = Voter::where('user_id', auth()->id())->first();
        if ($voter) {
            $voter->update($validated);
        }

        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $this->onboardingService->completePhase($progress, 'profile_setup', $validated);

        return redirect()->route('voter.onboarding.first-watch');
    }

    /**
     * First watch tutorial phase
     */
    public function firstWatch()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $phases = $this->onboardingService->getPhasesForType('voter');

        return view('standalone.voter.onboarding.first-watch', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
        ]);
    }

    public function completeFirstWatch(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $this->onboardingService->completePhase($progress, 'first_watch');

        return redirect()->route('voter.onboarding.payout');
    }

    /**
     * Payout setup phase
     */
    public function payoutSetup()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $phases = $this->onboardingService->getPhasesForType('voter');
        $voter = Voter::where('user_id', auth()->id())->first();

        return view('standalone.voter.onboarding.payout', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
            'voter' => $voter,
        ]);
    }

    public function completePayoutSetup(Request $request)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:paypal,cashapp',
            'paypal_email' => 'nullable|required_if:payment_method,paypal|email|max:255',
            'cashapp_tag' => 'nullable|required_if:payment_method,cashapp|string|max:100',
        ]);

        // Update voter payout info
        $voter = Voter::where('user_id', auth()->id())->first();
        if ($voter) {
            $method = $validated['payment_method'];

            $voter->update([
                'payment_method' => $method,
                'paypal_email' => $method === 'paypal' ? ($validated['paypal_email'] ?? null) : null,
                'cashapp_tag' => $method === 'cashapp' ? ltrim((string) ($validated['cashapp_tag'] ?? ''), '$') : null,
            ]);
        }

        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $this->onboardingService->completePhase($progress, 'payout_setup', $validated);

        return redirect()->route('voter.onboarding.referrals');
    }

    /**
     * Referral setup phase
     */
    public function referralSetup()
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $phases = $this->onboardingService->getPhasesForType('voter');
        $voter = Voter::where('user_id', auth()->id())->first();

        return view('standalone.voter.onboarding.referrals', [
            'progress' => $progress,
            'phases' => $phases,
            'totalPhases' => count($phases),
            'voter' => $voter,
        ]);
    }

    public function completeReferralSetup(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $this->onboardingService->completePhase($progress, 'referral_setup');

        // Onboarding complete! Redirect to dashboard
        return redirect()->route('voter.dashboard')->with('success', 'Onboarding complete! Welcome to U9itus.');
    }

    /**
     * Skip onboarding
     */
    public function skip(Request $request)
    {
        $progress = $this->onboardingService->getOrCreate(auth()->user(), 'voter');
        $this->onboardingService->skipOnboarding($progress);

        return redirect()->route('voter.dashboard')->with('info', 'Onboarding skipped. You can access tutorial resources anytime.');
    }
}
