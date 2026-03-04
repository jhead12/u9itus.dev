<?php

namespace App\Http\Middleware;

use App\Services\OnboardingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckVoterOnboarding
{
    public function __construct(
        protected OnboardingService $onboardingService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip check if not authenticated or not a voter
        if (!$user || !$user->hasRole('voter')) {
            return $next($request);
        }

        // Skip check if already on onboarding routes
        if ($request->routeIs('voter.onboarding.*')) {
            return $next($request);
        }

        // Check if onboarding is required
        if ($this->onboardingService->isOnboardingRequired($user, 'voter')) {
            $progress = $this->onboardingService->getOrCreate($user, 'voter');
            $nextPhase = $this->onboardingService->getNextRequiredPhase($progress);

            if ($nextPhase) {
                $phases = $this->onboardingService->getPhasesForType('voter');
                $route = $phases[$nextPhase]['route'] ?? 'voter.onboarding.welcome';
                return redirect()->route($route);
            }
        }

        return $next($request);
    }
}
