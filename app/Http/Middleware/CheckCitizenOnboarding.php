<?php

namespace App\Http\Middleware;

use App\Services\OnboardingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCitizenOnboarding
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

        // Skip check if not authenticated or not a citizen
        if (!$user || !$user->hasRole('citizen')) {
            return $next($request);
        }

        // Skip check if already on onboarding routes
        if ($request->routeIs('citizen.onboarding.*')) {
            return $next($request);
        }

        // Check if onboarding is required
        if ($this->onboardingService->isOnboardingRequired($user, 'citizen')) {
            $progress = $this->onboardingService->getOrCreate($user, 'citizen');
            $nextPhase = $this->onboardingService->getNextRequiredPhase($progress);

            if ($nextPhase) {
                $phases = $this->onboardingService->getPhasesForType('citizen');
                $route = $phases[$nextPhase]['route'] ?? 'citizen.dashboard';
                return redirect()->route($route);
            }
        }

        return $next($request);
    }
}
